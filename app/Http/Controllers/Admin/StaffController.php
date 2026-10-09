<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    /**
     * スタッフ一覧（管理者以外のユーザー）。
     */
    public function index(): View
    {
        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        return view('admin.staff-list', ['users' => $users]);
    }

    /**
     * スタッフ別の月次勤怠一覧。
     *
     * @param  Request  $request  date（YYYY-MM）。不正または未指定なら当月
     * @param  int  $id  スタッフ（ユーザー）ID。管理者のIDは 404
     */
    public function attendance(Request $request, int $id): View
    {
        $user = User::where('admin_status', false)->findOrFail($id);

        $date = $this->resolveMonth($request->query('date'));

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $this->monthlyRecords($user, $date),
        ]);
    }

    /**
     * スタッフ別の月次勤怠をCSVでダウンロードする（Excel用にBOM付きUTF-8）。
     *
     * @param  ExportAttendanceRequest  $request  user_id と year_month（YYYY-MM）
     */
    public function export(ExportAttendanceRequest $request): StreamedResponse
    {
        $user = User::where('admin_status', false)->findOrFail($request->validated('user_id'));
        $month = Carbon::createFromFormat('!Y-m', $request->validated('year_month'));

        $rows = $this->monthlyRecords($user, $month);
        $filename = sprintf('attendance_%d_%s.csv', $user->id, $month->format('Ym'));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel用のBOM
            fputcsv($out, ['日付', '出勤', '退勤', '休憩', '合計']);

            // each() はコールバックが false を返すと打ち切るため、fputcsv の戻り値は返さない
            $rows->each(function (array $row) use ($out): void {
                fputcsv($out, [
                    $row['date'],
                    $row['clock_in'],
                    $row['clock_out'],
                    $this->formatDuration($row['total_break_time']),
                    $this->formatDuration($row['total_time']),
                ]);
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ?date=YYYY-mm を月初のCarbonにする。不正な値は当月に戻す
    private function resolveMonth(?string $month): Carbon
    {
        $isValid = Validator::make(['date' => $month], ['date' => 'required|date_format:Y-m'])->passes();

        return $isValid
            ? Carbon::createFromFormat('!Y-m', $month)
            : now()->startOfMonth();
    }

    /**
     * 月内の全日付を行にして、勤怠がある日は値を入れる（画面とCSVで共用）。
     * 休憩の合計を求めるため breaks を eager load する（N+1 防止）。
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function monthlyRecords(User $user, Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $records = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceRecord $record) => Carbon::parse($record->date)->toDateString());

        return collect(CarbonPeriod::create($start, $end))
            ->map(function (Carbon $day) use ($records) {
                $record = $records->get($day->toDateString());

                return [
                    'id' => $record?->id,
                    'date' => $day->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $record?->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
                    'clock_out' => $record?->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                    'total_break_time' => $record?->total_break_time,
                    'total_time' => $record?->total_time,
                ];
            });
    }

    // "01:30:00" → "1:30"（画面と同じ G:i 表示）。値が無ければ空文字
    private function formatDuration(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('G:i') : '';
    }
}
