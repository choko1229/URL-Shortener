<?php

declare(strict_types=1);

namespace App\Providers;

use App\Installer\EnvironmentFile;
use App\Installer\InstallationState;
use App\Models\User;
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
use App\Support\ExternalServiceKeys;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
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
            static fn (Application $app): CountryResolver => new CountryResolver(self::config($app, 'shortener.geoip_database')),
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

        $this->registerUpdateServices();
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // 開発時は遅延ロード・未定義属性アクセス等を例外にして早期に検知する
        Model::shouldBeStrict(! $this->app->isProduction());

        // 管理者のみの機能（requirements.md 4-2）
        Gate::define('admin', static fn (User $user): bool => $user->isAdmin());
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
