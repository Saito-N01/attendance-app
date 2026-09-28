<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
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
            $record->update(['clock_in' => $now->format('H:i:s')]);
        }
    }

    private function breakIn(AttendanceRecord $record, Carbon $now): void
    {
        if ($record->status === '出勤中') {
            $record->breaks()->create(['break_in' => $now->format('H:i:s')]);
        }
    }

    private function breakOut(AttendanceRecord $record, Carbon $now): void
    {
        if ($record->status === '休憩中') {
            $record->breaks->firstWhere('break_out', null)
                ?->update(['break_out' => $now->format('H:i:s')]);
        }
    }

    private function clockOut(AttendanceRecord $record, Carbon $now): void
    {
        if ($record->status === '出勤中') {
            $record->update(['clock_out' => $now->format('H:i:s')]);
        }
    }
}
