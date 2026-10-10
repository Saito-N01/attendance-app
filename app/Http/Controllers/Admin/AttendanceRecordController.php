<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RestoresBreakInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AttendanceRecordController extends Controller
{
    use RestoresBreakInput;

    /**
     * 日次の勤怠一覧（全スタッフ）。?date=YYYY-MM-DD の日の勤怠と、そのスタッフを取得する。
     * 休憩の合計を求めるため breaks を eager load する（N+1 防止）。
     *
     * @param  Request  $request  date（YYYY-MM-DD）。不正または未指定なら今日
     */
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->query('date'));

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('date', $date->toDateString())
            ->get();

        $users = User::whereIn('id', $attendanceRecords->pluck('user_id'))
            ->orderBy('id')
            ->get();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->toDateString(),
            'nextDay' => $date->copy()->addDay()->toDateString(),
            'users' => $users,
            'attendanceRecords' => $attendanceRecords,
        ]);
    }

    // ?date=YYYY-mm-dd をCarbonにする。不正な値は今日に戻す
    private function resolveDate(?string $date): Carbon
    {
        $isValid = Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->passes();

        return $isValid
            ? Carbon::createFromFormat('!Y-m-d', $date)
            : now()->startOfDay();
    }

    /**
     * 勤怠詳細（管理者用）。バリデーションエラーで戻ってきたときは、入力内容を復元して表示する。
     *
     * @param  int  $id  勤怠ID
     */
    public function show(int $id): View
    {
        $record = AttendanceRecord::with(['user', 'breaks'])->findOrFail($id);
        $date = Carbon::parse($record->date);

        $breaks = $record->breaks->map(fn ($break) => [
            'break_in' => $this->formatTime($break->break_in),
            'break_out' => $this->formatTime($break->break_out),
        ])->all();

        // バリデーションエラーで戻ってきたときは、入力内容を復元する（Blade側はold()を使わないため）
        $breaks = $this->restoreBreaksFromOldInput() ?? $breaks;

        return view('admin.admin-detail', [
            'user' => $record->user,
            'attendanceRecord' => [
                'id' => $record->id,
                'year' => $date->isoFormat('YYYY年'),
                'date' => $date->isoFormat('M月D日'),
                'clock_in' => old('new_clock_in', $this->formatTime($record->clock_in)),
                'clock_out' => old('new_clock_out', $this->formatTime($record->clock_out)),
                'breaks' => $breaks,
                'comment' => old('comment', $record->comment),
            ],
        ]);
    }

    /**
     * 勤怠を直接修正する（承認フローなし）。出退勤・備考を更新し、休憩は入力内容で置き換える。
     * 更新と休憩の入れ替えは1つのトランザクションで行う。
     *
     * @param  UpdateAttendanceRequest  $request  検証済みの修正内容
     * @param  int  $id  勤怠ID
     */
    public function update(UpdateAttendanceRequest $request, int $id): RedirectResponse
    {
        $record = AttendanceRecord::findOrFail($id);

        DB::transaction(function () use ($request, $record) {
            $record->update([
                'clock_in' => $request->input('new_clock_in'),
                'clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);

            $record->breaks()->delete();

            collect($request->input('new_break_in', []))
                ->map(fn ($in, $i) => ['break_in' => $in, 'break_out' => $request->input("new_break_out.$i")])
                ->reject(fn ($break) => $break['break_in'] === null && $break['break_out'] === null)
                ->each(fn ($break) => $record->breaks()->create($break));
        });

        return redirect("/admin/attendance/{$id}")->with('message', '勤怠を修正しました。');
    }

    private function formatTime(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('H:i') : '';
    }
}
