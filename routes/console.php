<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
| 定期実行。cron（`php artisan schedule:run` を毎分）を設定した場合に使われる。
| cron が無くても、サイトへのアクセスをきっかけに同じ処理が 1 日 1 回実行される（App\Services\Tasks\WebCron）。
*/

// 自動アップデートの確認（requirements.md 7-1: 1 日 1 回。日本時間 4:00 を過ぎたら実行）
Schedule::command('app:periodic-tasks')
    ->everyMinute()
    ->withoutOverlapping(120);
