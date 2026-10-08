<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 存在しない ID（ルートモデルバインディング失敗）→ 404
        // Laravel は ModelNotFoundException を NotFoundHttpException に変換してからここへ渡す
        $this->renderable(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['error' => '勤怠情報が見つかりませんでした。'], 404);
            }

            return null;
        });

        // Policy で拒否（$this->authorize() 失敗）→ 403
        // AuthorizationException は AccessDeniedHttpException に変換されてここへ渡る
        $this->renderable(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') && $e->getPrevious() instanceof AuthorizationException) {
                return response()->json(['error' => 'この操作を実行する権限がありません。'], 403);
            }

            return null;
        });
    }

    /**
     * api/* は Accept ヘッダーの有無にかかわらず JSON で返す。
     * これが無いと、Accept: application/json を付け忘れたリクエストの 401 が
     * ログイン画面へのリダイレクト（302）になってしまう。
     * 401（AuthenticationException）と 422（ValidationException）はこの判定で JSON になる。
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }
}
