<?php

declare(strict_types=1);

namespace App\Providers;

use App\Installer\EnvironmentFile;
use App\Installer\InstallationState;
use App\Services\Redirect\CountryResolver;
use App\Support\ExternalServiceKeys;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 設定値・外部サービスのキーはリクエスト単位でキャッシュする
        $this->app->scoped(ShortenerSettings::class);
        $this->app->scoped(ExternalServiceKeys::class);
        $this->app->singleton(ShortUrlBuilder::class);

        $this->app->scoped(
            CountryResolver::class,
            static fn (Application $app): CountryResolver => new CountryResolver((string) $app->make('config')->get('shortener.geoip_database')),
        );

        $this->app->singleton(
            InstallationState::class,
            static fn (Application $app): InstallationState => new InstallationState($app->storagePath(InstallationState::LOCK_FILE)),
        );
        // .env の場所は実行時に変わりうる（テスト等）ため都度解決する
        $this->app->bind(
            EnvironmentFile::class,
            static fn (Application $app): EnvironmentFile => new EnvironmentFile($app->environmentFilePath()),
        );
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // 開発時は遅延ロード・未定義属性アクセス等を例外にして早期に検知する
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
