<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Carbon\Carbon;

class StampCorrectionRequestController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $formattedApplications = Application::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Application $application) => [
                'id' => $application->id,
                'approval_status' => $application->status === Application::STATUS_PENDING ? '承認待ち' : '承認済み',
                'date' => Carbon::parse($application->date)->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => $application->created_at->format('Y/m/d'),
            ]);

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $formattedApplications,
        ]);
    }

    public function show($id)
    {
        // 自分の申請のみ（他人のIDは404）
        $application = Application::where('user_id', auth()->id())->findOrFail($id);

        return redirect("/attendance/detail/{$application->attendance_record_id}");
    }
}
