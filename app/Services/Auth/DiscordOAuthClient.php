<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Support\ExternalServiceKeys;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Discord OAuth2（Authorization Code フロー・identify スコープ） */
final class DiscordOAuthClient
{
    private const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';

    private const TOKEN_URL = 'https://discord.com/api/oauth2/token';

    private const USER_URL = 'https://discord.com/api/users/@me';

    private const SCOPE = 'identify';

    private const TIMEOUT_SECONDS = 10;

    public function __construct(private readonly ExternalServiceKeys $keys) {}

    public function isConfigured(): bool
    {
        return $this->keys->discordClientId() !== null && $this->keys->discordClientSecret() !== null;
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->keys->discordClientId(),
            'scope' => self::SCOPE,
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'prompt' => 'none',
        ], encoding_type: PHP_QUERY_RFC3986);
    }

    /** @throws DiscordAuthException */
    public function exchangeCode(string $code, string $redirectUri): string
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(self::TIMEOUT_SECONDS)->post(self::TOKEN_URL, [
                'client_id' => $this->keys->discordClientId(),
                'client_secret' => $this->keys->discordClientSecret(),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Discord のトークン取得で通信エラーが発生しました。', ['exception' => $e::class]);

            throw new DiscordAuthException('Discord に接続できませんでした。時間をおいて再度お試しください。');
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            Log::warning('Discord のトークン取得に失敗しました。', ['status' => $response->status(), 'error' => $response->json('error')]);

            throw new DiscordAuthException('Discord ログインに失敗しました。もう一度お試しください。');
        }

        return $token;
    }

    /** @throws DiscordAuthException */
    public function fetchProfile(string $accessToken): DiscordProfile
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(self::TIMEOUT_SECONDS)->get(self::USER_URL);
        } catch (ConnectionException $e) {
            Log::warning('Discord のユーザー情報取得で通信エラーが発生しました。', ['exception' => $e::class]);

            throw new DiscordAuthException('Discord に接続できませんでした。時間をおいて再度お試しください。');
        }

        $id = $response->json('id');
        $username = $response->json('username');

        if (! $response->successful() || ! is_string($id) || preg_match('/\A\d{1,32}\z/', $id) !== 1 || ! is_string($username) || $username === '') {
            Log::warning('Discord のユーザー情報を取得できませんでした。', ['status' => $response->status()]);

            throw new DiscordAuthException('Discord のユーザー情報を取得できませんでした。');
        }

        $globalName = $response->json('global_name');
        $avatar = $response->json('avatar');

        return new DiscordProfile(
            id: $id,
            username: mb_substr($username, 0, 64),
            globalName: is_string($globalName) && $globalName !== '' ? mb_substr($globalName, 0, 64) : null,
            avatarHash: is_string($avatar) && $avatar !== '' ? mb_substr($avatar, 0, 64) : null,
        );
    }
}
