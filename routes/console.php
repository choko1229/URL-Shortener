<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
| 定期実行（サーバーの cron で `php artisan schedule:run` を毎分実行する）
*/

// 自動アップデート（requirements.md 7-1: 1 日 1 回 GitHub Releases を確認し、新しいリリースを自動適用）
Schedule::command('app:update')
    ->dailyAt('04:00')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping(120);
