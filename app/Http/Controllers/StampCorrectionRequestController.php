<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\StampCorrectionRequestController as AdminStampCorrectionRequestController;
use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StampCorrectionRequestController extends Controller
{
    /**
     * 申請一覧。一般ユーザーは自分の申請（新しい順）、管理者は全ユーザーの申請を表示する。
     */
    public function index(): View
    {
        $user = auth()->user();

        // 管理者は全ユーザーの申請一覧を表示する
        if ($user->admin_status) {
            return app(AdminStampCorrectionRequestController::class)->index();
        }

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

    /**
     * 申請から、対応する勤怠詳細へリダイレクトする。他人の申請は 404。
     *
     * @param  int  $id  申請ID
     */
    public function show(int $id): RedirectResponse
    {
        // 自分の申請のみ（他人のIDは404）
        $application = Application::where('user_id', auth()->id())->findOrFail($id);

        return redirect("/attendance/detail/{$application->attendance_record_id}");
    }
}
