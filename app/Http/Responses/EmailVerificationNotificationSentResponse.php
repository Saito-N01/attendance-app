<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse as EmailVerificationNotificationSentResponseContract;

class EmailVerificationNotificationSentResponse implements EmailVerificationNotificationSentResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return back()->with('message', '認証メールを再送しました。');
    }
}
