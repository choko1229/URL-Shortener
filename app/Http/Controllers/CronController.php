<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Tasks\PeriodicTasks;
use App\Services\Tasks\WebCron;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * WebCron が自分自身に送るリクエストの受け口（PHP-FPM 以外の環境用）。
 * 送信元は応答を待たずに切断するため、ignore_user_abort で処理を最後まで続ける。
 */
final class CronController extends Controller
{
    public function __invoke(Request $request, WebCron $webCron, PeriodicTasks $tasks): Response
    {
        abort_unless($webCron->isValidToken($request->input('token')), Response::HTTP_NOT_FOUND);

        ignore_user_abort(true);
        @set_time_limit(0);

        $tasks->runDue('web', CarbonImmutable::now());

        return response()->noContent();
    }
}
