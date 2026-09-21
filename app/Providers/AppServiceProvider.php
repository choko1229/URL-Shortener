<?php

declare(strict_types=1);

namespace App\Providers;

use App\Installer\EnvironmentFile;
use App\Installer\InstallationState;
use App\Installer\SchemaState;
use App\Models\SitePage;
use App\Models\User;
use App\Services\GeoIp\GeoIpDatabase;
use App\Services\Redirect\CountryResolver;
use App\Services\Update\AppVersion;
use App\Services\Update\ArtisanProcess;
use App\Services\Update\BackupManager;
use App\Services\Update\CodeTree;
use App\Services\Update\DatabaseBackup;
use App\Services\Update\GitHubReleaseClient;
use App\Services\Update\GitStrategy;
use App\Services\Update\PhpBinaryResolver;
use App\Services\Update\ReleaseZipStrategy;
use App\Services\Update\UpdateStrategy;
use App\Support\AccessPolicy;
use App\Support\ExternalServiceKeys;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\Support\SiteIcon;
use App\Support\SiteIdentity;
use App\Support\Theme;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 設定値・外部サービスのキーはリクエスト単位でキャッシュする
        $this->app->scoped(ShortenerSettings::class);
        $this->app->scoped(ExternalServiceKeys::class);
        $this->app->scoped(SiteIdentity::class);
        $this->app->scoped(Theme::class);
        $this->app->scoped(AccessPolicy::class);
        $this->app->scoped(
            SiteIcon::class,
            static fn (Application $app): SiteIcon => new SiteIcon($app->storagePath('app/private/branding')),
        );
        $this->app->singleton(ShortUrlBuilder::class);

        $this->app->bind(GeoIpDatabase::class, static fn (Application $app): GeoIpDatabase => new GeoIpDatabase(
            self::config($app, 'shortener.geoip.manual_database'),
            self::config($app, 'shortener.geoip.auto_database'),
        ));
        $this->app->scoped(
            CountryResolver::class,
            static fn (Application $app): CountryResolver => new CountryResolver($app->make(GeoIpDatabase::class)->activePath()),
        );

        $this->app->singleton(
            InstallationState::class,
            static fn (Application $app): InstallationState => new InstallationState($app->storagePath(InstallationState::LOCK_FILE)),
        );
        $this->app->singleton(
            SchemaState::class,
            static fn (Application $app): SchemaState => new SchemaState(
                $app->storagePath(SchemaState::FILE),
                $app->databasePath('migrations'),
            ),
        );
        // .env の場所は実行時に変わりうる（テスト等）ため都度解決する
        $this->app->bind(
            EnvironmentFile::class,
            static fn (Application $app): EnvironmentFile => new EnvironmentFile($app->environmentFilePath()),
        );

        $this->registerUpdateServices();
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // 開発時は遅延ロード・未定義属性アクセス等を例外にして早期に検知する
        Model::shouldBeStrict(! $this->app->isProduction());

        // 管理者のみの機能（requirements.md 4-2）
        Gate::define('admin', static fn (User $user): bool => $user->isAdmin());

        // サイト名などは設置した人が変更できるため、すべての画面から参照できるようにする
        View::composer('*', static function (ViewContract $view): void {
            $view->with('site', app(SiteIdentity::class));
            $view->with('theme', app(Theme::class));
            $view->with('siteIcon', app(SiteIcon::class));
        });

        // フッターには、用意されている固定ページだけを並べる
        View::composer('components.layouts.main', static function (ViewContract $view): void {
            $view->with('footerPages', SitePage::menu());
        });
    }

    /** 自動アップデート（requirements.md 7 章） */
    private function registerUpdateServices(): void
    {
        $this->app->singleton(AppVersion::class, static fn (Application $app): AppVersion => new AppVersion($app->basePath()));

        $this->app->bind(
            DatabaseBackup::class,
            static fn (Application $app): DatabaseBackup => new DatabaseBackup(null, self::config($app, 'shortener.update.mysqldump_binary')),
        );

        $this->app->bind(BackupManager::class, static fn (Application $app): BackupManager => new BackupManager(
            $app->basePath(),
            self::config($app, 'shortener.update.backup_path'),
            (int) $app->make('config')->get('shortener.update.backup_generations', 3),
            $app->make(DatabaseBackup::class),
            $app->make(CodeTree::class),
        ));

        $this->app->singleton(
            PhpBinaryResolver::class,
            static fn (Application $app): PhpBinaryResolver => new PhpBinaryResolver($app->make('config')->get('shortener.update.php_binary')),
        );

        $this->app->bind(ArtisanProcess::class, static fn (Application $app): ArtisanProcess => new ArtisanProcess(
            $app->basePath(),
            $app->make(PhpBinaryResolver::class),
            $app->make(Kernel::class),
        ));

        // Git で設置した環境は git / Composer で、配布用 zip で設置した環境は zip の入れ替えで更新する
        $this->app->bind(UpdateStrategy::class, static fn (Application $app): UpdateStrategy => $app->make(AppVersion::class)->isGitCheckout()
            ? new GitStrategy($app->basePath(), self::config($app, 'shortener.update.git_binary'), self::config($app, 'shortener.update.composer_binary'))
            : new ReleaseZipStrategy($app->basePath(), self::config($app, 'shortener.update.work_path'), $app->make(GitHubReleaseClient::class), $app->make(CodeTree::class)));
    }

    private static function config(Application $app, string $key): string
    {
        return (string) $app->make('config')->get($key);
    }
}
