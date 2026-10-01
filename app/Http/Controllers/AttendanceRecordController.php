<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Application;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceRecordController extends Controller
{
    public function create()
    {
        $now = Carbon::now();
        $user = auth()->user();

        $record = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->where('date', $now->toDateString())
            ->first();

        $user->attendance_status = $record?->status ?? '勤務外';

        return view('user.attendance-register', [
            'user' => $user,
            'formattedDate' => $now->isoFormat('YYYY年M月D日(ddd)'),
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'action' => ['required', 'in:clock_in,break_in,break_out,clock_out'],
        ]);

        $now = Carbon::now();
        $record = AttendanceRecord::with('breaks')->firstOrCreate([
            'user_id' => auth()->id(),
            'date' => $now->toDateString(),
        ]);

        match ($request->input('action')) {
            'clock_in' => $this->clockIn($record, $now),
            'break_in' => $this->breakIn($record, $now),
            'break_out' => $this->breakOut($record, $now),
            'clock_out' => $this->clockOut($record, $now),
        };

        return redirect('/attendance');
    }

    private function clockIn(AttendanceRecord $record, Carbon $now): void
    {
        if ($record->status === '勤務外') {
            $record->update(['clock_in' => $now->format('H:i')]);
        }
    }

    private function breakIn(AttendanceRecord $record, Carbon $now): void
    {
        if ($record->status === '出勤中') {
            $record->breaks()->create(['break_in' => $now->format('H:i')]);
        }
    }

    private function breakOut(AttendanceRecord $record, Carbon $now): void
    {
        if ($record->status === '休憩中') {
            $record->breaks->firstWhere('break_out', null)
                ?->update(['break_out' => $now->format('H:i')]);
        }
    }

    private function clockOut(AttendanceRecord $record, Carbon $now): void
    {
        if ($record->status === '出勤中') {
            $record->update(['clock_out' => $now->format('H:i')]);
        }
    }

    public function index(Request $request)
    {
        $date = $this->resolveMonth($request->query('date'));
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $records = AttendanceRecord::with('breaks')
            ->where('user_id', auth()->id())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($record) => Carbon::parse($record->date)->toDateString());

        $formattedAttendanceRecords = collect(CarbonPeriod::create($start, $end))
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

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
    }

    // ?date=YYYY-mm を月初のCarbonにする。不正な値は当月に戻す
    private function resolveMonth(?string $month): Carbon
    {
        $isValid = Validator::make(['date' => $month], ['date' => 'required|date_format:Y-m'])->passes();

        return $isValid
            ? Carbon::createFromFormat('!Y-m', $month)
            : now()->startOfMonth();
    }

    public function show($id)
    {
        $user = auth()->user();

        // 管理者が /attendance/{id}を踏んだ場合は管理者用画面へ
        if ($user->admin_status) {
            return redirect("/admin/attendance/{$id}");
        }

        $record = AttendanceRecord::with(['breaks', 'pendingApplication.breaks'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $application = $record->pendingApplication;
        $date = Carbon::parse($record->date);

        if ($application) {
            // 承認待ち：申請内容を閲覧のみで表示
            $clockIn = $application->new_clock_in;
            $clockOut = $application->new_clock_out;
            $comment = $application->comment;
            $breaks = $application->breaks->map(fn ($b) => [
                'break_in' => $this->formatTime($b->new_break_in),
                'break_out' => $this->formatTime($b->new_break_out),
            ])->all();
        } else {
            $clockIn = $record->clock_in;
            $clockOut = $record->clock_out;
            $comment = $record->comment;
            $breaks = $record->breaks->map(fn ($b) => [
                'break_in' => $this->formatTime($b->break_in),
                'break_out' => $this->formatTime($b->break_out),
            ])->all();

            // バリデーションエラーで戻ってきたときは、入力内容を復元する（Blade側はold()を使わないため）
            if (old('new_break_in') !== null) {
                $breaks = [];
                foreach (old('new_break_in') as $i => $in) {
                    $breaks[] = ['break_in' => $in ?? '', 'break_out' => old("new_break_out.$i") ?? ''];
                }
                // 末尾の空行はBladeが追加入力用に出すので取り除く
                while ($breaks && $breaks[array_key_last($breaks)] === ['break_in' => '', 'break_out' => '']) {
                    array_pop($breaks);
                }
            }
        }

        return view('user.user-detail', [
            'user' => $user,
            'data' => [
                'id' => $record->id,
                'application' => $application,
                'year' => $date->isoFormat('YYYY年'),
                'date' => $date->isoFormat('M月D日'),
                'clock_in' => $application ? $this->formatTime($clockIn) : old('new_clock_in', $this->formatTime($clockIn)),
                'clock_out' => $application ? $this->formatTime($clockOut) : old('new_clock_out', $this->formatTime($clockOut)),
                'breaks' => $breaks,
                'comment' => $application ? $comment : old('comment', $comment),
            ],
        ]);
    }

    public function update(UpdateAttendanceRequest $request, $id)
    {
        // 自分の勤怠のみ
        $record = AttendanceRecord::with('pendingApplication')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // 承認待ちがあれば新規申請は受け付けない
        if ($record->pendingApplication) {
            return redirect("/attendance/detail/{$id}");
        }

        DB::transaction(function () use ($request, $record) {
            $application = $record->applications()->create([
                'user_id' => auth()->id(),
                'date' => Carbon::parse($record->date)->toDateString(), // new_date は使わない
                'new_clock_in' => $request->input('new_clock_in'),
                'new_clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
                'status' => Application::STATUS_PENDING,
            ]);

            foreach ((array) $request->input('new_break_in', []) as $i => $in) {
                $out = $request->input("new_break_out.$i");
                if ($in === null && $out === null) {
                    continue; // 追加用の空行は保存しない
                }
                $application->breaks()->create(['new_break_in' => $in, 'new_break_out' => $out]);
            }
        });

        return redirect("/attendance/detail/{$id}");
    }

    private function formatTime(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('H:i') : '';
    }
}
