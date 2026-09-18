<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理者専用 API の認証（requirements.md 5: API キー方式、管理者のみ利用可能）。
 * Authorization: Bearer ヘッダーのキーを照合し、発行者が現在も管理者であることを確認する。
 */
final class AuthenticateApiKey
{
    // 最終使用日時の更新は 1 分に 1 回まで（毎リクエストの書き込みを避ける）
    private const TOUCH_INTERVAL_SECONDS = 60;

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return self::error('API キーが指定されていません。Authorization: Bearer ヘッダーを付けてください。', Response::HTTP_UNAUTHORIZED);
        }

        $key = ApiKey::query()->active()->with('user')->where('key_hash', ApiKey::hash($token))->first();

        if ($key === null || $key->user === null) {
            Log::notice('無効な API キーでアクセスされました。', ['ip' => $request->ip()]);

            return self::error('API キーが無効です。', Response::HTTP_UNAUTHORIZED);
        }

        if (! $key->user->isAdmin()) {
            return self::error('API は管理者のみ利用できます。', Response::HTTP_FORBIDDEN);
        }

        $now = CarbonImmutable::now();
        if ($key->last_used_at === null || $key->last_used_at->addSeconds(self::TOUCH_INTERVAL_SECONDS)->lessThan($now)) {
            $key->last_used_at = $now;
            $key->save();
        }

        $user = $key->user;
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private static function error(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status)->header('WWW-Authenticate', 'Bearer');
    }
}
