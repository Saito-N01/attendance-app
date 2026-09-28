<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

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
        $isValid = validator(['date' => $month], ['date' => 'required|date_format:Y-m'])->passes();

        return $isValid
            ? Carbon::createFromFormat('!Y-m', $month)
            : now()->startOfMonth();
    }
}
