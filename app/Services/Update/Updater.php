<?php

declare(strict_types=1);

namespace App\Services\Update;

use App\Enums\UpdateRunStatus;
use App\Models\UpdateRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 自動アップデート（requirements.md 7 章 案A: 完全自動適用）。
 * 1. GitHub Releases を確認 → 2. 新しいリリースならバックアップ（DB・コード、3世代）→ 3. コードを更新
 * → 4. マイグレーション → 5. ヘルスチェック → 成功なら通知 / 失敗ならコードと DB を戻して通知
 */
final class Updater
{
    private const LOCK_KEY = 'url-shortener:update';

    private const LOCK_SECONDS = 3600;

    public function __construct(
        private readonly UpdateSettings $settings,
        private readonly AppVersion $version,
        private readonly GitHubReleaseClient $github,
        private readonly BackupManager $backups,
        private readonly DatabaseBackup $database,
        private readonly UpdateStrategy $strategy,
        private readonly ArtisanProcess $artisan,
        private readonly DiscordWebhookNotifier $notifier,
    ) {}

    public function check(): UpdateCheckResult
    {
        $current = $this->version->current();
        $token = $this->settings->githubToken();

        try {
            return new UpdateCheckResult($current, $this->github->latest($this->settings->repository(), $token), null);
        } catch (UpdateException $e) {
            return new UpdateCheckResult($current, null, $e->getMessage());
        }
    }

    /** @param  bool  $manual  true なら自動アップデートが無効に設定されていても実行する */
    public function run(bool $manual = false): UpdateOutcome
    {
        if (! $manual && ! $this->settings->enabled()) {
            return UpdateOutcome::skipped('自動アップデートは無効に設定されています。');
        }

        $token = $this->settings->githubToken();
        $current = $this->version->current();
        if ($current === null) {
            return UpdateOutcome::skipped('現在のバージョンを判定できないため、更新しません（開発中のコードなど）。');
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);
        if (! $lock->get()) {
            return UpdateOutcome::skipped('別の更新処理が実行中です。');
        }

        try {
            try {
                $release = $this->github->latest($this->settings->repository(), $token);
            } catch (UpdateException $e) {
                Log::warning('最新リリースを確認できませんでした。', ['error' => $e->getMessage()]);

                return UpdateOutcome::skipped($e->getMessage());
            }

            if (! $release->version->isNewerThan($current)) {
                return UpdateOutcome::skipped("最新の状態です（{$current->toString()}）。");
            }

            return $this->update($current, $release, $token);
        } finally {
            $lock->release();
        }
    }

    private function update(Version $current, ReleaseInfo $release, ?string $token): UpdateOutcome
    {
        $from = $current->toString();
        $run = UpdateRun::query()->create([
            'from_version' => $from,
            'to_version' => $release->tag,
            'strategy' => $this->strategy->name(),
            'status' => UpdateRunStatus::Running,
            'started_at' => CarbonImmutable::now(),
        ]);

        Log::notice('自動アップデートを開始します。', ['from' => $from, 'to' => $release->tag, 'strategy' => $this->strategy->name()]);

        try {
            $backup = $this->backups->create($from, $this->strategy->currentRevision(), CarbonImmutable::now());
            $run->backup_path = $backup->path;
            $run->save();
        } catch (Throwable $e) {
            return $this->finish($run, UpdateRunStatus::Failed, "バックアップを作成できなかったため、更新を中止しました（{$from} のまま）。原因: {$e->getMessage()}");
        }

        try {
            $this->strategy->apply($release, $token);
            self::resetOpcache();
            // 以降は新しいコードで実行する
            $this->artisan->run(['optimize:clear']);
            $this->artisan->run(['migrate', '--force']);
            $this->artisan->run(['app:health-check']);
        } catch (Throwable $e) {
            Log::error('自動アップデートに失敗しました。ロールバックします。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            return $this->rollback($run, $backup, $from, $e);
        }

        return $this->finish($run, UpdateRunStatus::Succeeded, "{$from} から {$release->tag} に更新しました。");
    }

    private function rollback(UpdateRun $run, Backup $backup, string $from, Throwable $cause): UpdateOutcome
    {
        try {
            $this->strategy->rollback($backup);
            self::resetOpcache();
            $this->database->restore($backup->databasePath());
            $this->artisan->run(['optimize:clear']);
        } catch (Throwable $e) {
            Log::critical('ロールバックに失敗しました。', ['exception' => $e::class, 'error' => $e->getMessage(), 'backup' => $backup->path]);

            return $this->finish(
                $run,
                UpdateRunStatus::RollbackFailed,
                "更新に失敗し、ロールバックにも失敗しました。手動で復旧してください（バックアップ: {$backup->path}）。更新の失敗: {$cause->getMessage()} / ロールバックの失敗: {$e->getMessage()}",
            );
        }

        return $this->finish($run, UpdateRunStatus::RolledBack, "更新に失敗したため {$from} に戻しました。原因: {$cause->getMessage()}");
    }

    /** Web サーバーの PHP がキャッシュした古いコードを破棄する（使えない環境では何もしない） */
    private static function resetOpcache(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function finish(UpdateRun $run, UpdateRunStatus $status, string $message): UpdateOutcome
    {
        $run->status = $status;
        $run->message = mb_substr($message, 0, 5000);
        $run->finished_at = CarbonImmutable::now();
        $run->save();

        $status === UpdateRunStatus::Succeeded
            ? Log::notice('自動アップデートが完了しました。', ['message' => $message])
            : Log::error('自動アップデートが失敗しました。', ['status' => $status->value, 'message' => $message]);

        $this->notifier->send($this->notifier->prefix()."自動アップデート {$status->label()}: {$message}");

        return UpdateOutcome::finished($status, $message);
    }
}
