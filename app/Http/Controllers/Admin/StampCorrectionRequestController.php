<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AttendanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StampCorrectionRequestController extends Controller
{
    public function index(): View
    {
        $applications = Application::with(['user', 'attendanceRecord'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.admin-application-list', ['applications' => $applications]);
    }

    public function show(int $id): View
    {
        $application = Application::with(['user', 'breaks'])->findOrFail($id);

        return view('admin.admin-application-detail', [
            'application' => $application,
            'user' => $application->user,
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        DB::transaction(function () use ($id) {
            // 同時に二重承認されないよう、行をロックしてから状態を確認する
            $application = Application::with('breaks')->lockForUpdate()->findOrFail($id);

            if ($application->status !== Application::STATUS_PENDING) {
                return;
            }

            // 勤怠本体と休憩を、申請内容に置き換える
            $record = AttendanceRecord::findOrFail($application->attendance_record_id);
            $record->update([
                'clock_in' => $application->new_clock_in,
                'clock_out' => $application->new_clock_out,
                'comment' => $application->comment,
            ]);

            $record->breaks()->delete();
            $application->breaks->each(fn ($break) => $record->breaks()->create([
                'break_in' => $break->new_break_in,
                'break_out' => $break->new_break_out,
            ]));

            $application->update([
                'status' => Application::STATUS_APPROVED,
                'approved_at' => now(),
            ]);
        });

        return redirect("/stamp_correction_request/approve/{$id}");
    }
}
