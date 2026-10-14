<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * ヘッダーのロゴ（href="/"）の遷移先。
 * トップページ自体は持たず、ログイン状態に合わせて「その人の最初の画面」へ振り分ける。
 */
class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect('/login');
        }

        // 管理者は管理者の勤怠一覧へ、一般ユーザーは打刻画面へ
        // （メール未認証の一般ユーザーは、打刻画面側の verified ミドルウェアが認証誘導画面へ案内する）
        return Auth::user()->admin_status
            ? redirect()->route('admin.attendance.list')
            : redirect('/attendance');
    }
}
