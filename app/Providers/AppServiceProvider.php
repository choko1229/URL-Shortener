<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 設定値はリクエスト単位でキャッシュする
        $this->app->scoped(ShortenerSettings::class);
        $this->app->singleton(ShortUrlBuilder::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // 開発時は遅延ロード・未定義属性アクセス等を例外にして早期に検知する
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
