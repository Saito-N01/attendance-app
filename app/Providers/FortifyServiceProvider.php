<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\EmailVerificationNotificationSentResponse;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse as EmailVerificationNotificationSentResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        Fortify::registerView(function () {
            return view('user.register');
        });

        // 一般ユーザー（/login）と管理者（/admin/login）で同じ Fortify のコントローラーを使い、
        // 画面・バリデーション・認証条件だけを /admin/* かどうかで切り替える
        Fortify::loginView(function (Request $request) {
            return $request->is('admin/*') ? view('admin.admin-login') : view('user.user-login');
        });

        $this->app->bind(FortifyLoginRequest::class, function ($app) {
            return $app['request']->is('admin/*') ? new AdminLoginRequest : new LoginRequest;
        });

        // 管理者ログインでは admin_status = true のユーザーだけを認証する（一致しなければ「ログイン情報が登録されていません」）
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', $request->input('email'))->first();

            if (! $user || ! Hash::check($request->input('password'), $user->password)) {
                return null;
            }

            if ($request->is('admin/*') && ! $user->admin_status) {
                return null;
            }

            return $user;
        });

        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);

        Fortify::verifyEmailView(function () {
            return view('auth.verify-email');
        });

        $this->app->singleton(
            EmailVerificationNotificationSentResponseContract::class,
            EmailVerificationNotificationSentResponse::class
        );
    }
}
