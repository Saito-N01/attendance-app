<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * ログアウト後の遷移先。管理者（/admin/logout）は管理者ログイン画面へ、一般ユーザーはログイン画面へ。
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect($request->is('admin/*') ? '/admin/login' : '/login');
    }
}
