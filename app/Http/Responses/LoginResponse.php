<?php

namespace App\Http\Responses;

use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * ログイン後の遷移先。管理者ログイン（/admin/login）は管理者の勤怠一覧へ、一般ユーザーは打刻画面へ。
     */
    public function toResponse($request): RedirectResponse
    {
        if ($request->is('admin/*')) {
            return redirect('/admin/attendance/list');
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
