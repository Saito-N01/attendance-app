<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 開発・テスト中だけ、リレーションの遅延読み込み（N+1 の原因）を例外にして気づけるようにする。
        // 本番では例外にせず、画面を止めない。
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
