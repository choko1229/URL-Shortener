<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Support\ExternalServiceKeys;
use App\Support\ShortenerSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * reCAPTCHA（スコアベース）の検証（requirements.md 4-4）。未ログインの発行にのみ使う。
 * Google Cloud の reCAPTCHA API で「評価」を作成して判定する（Google が推奨する CreateAssessment 方式）。
 * サーバーに Google Cloud のライブラリや認証ファイルを置かずに済むよう、REST API を API キーで呼び出す。
 * キーが未設定なら検証しない。Google 側の障害時は発行を止めない（レート制限・月間上限で抑止する）。
 */
final class RecaptchaVerifier
{
    // 判定する操作の名前（Google の評価で、想定どおりの操作かを確かめるために使う）
    public const ACTION_SHORTEN = 'shorten';

    public const ACTION_CONTACT = 'contact';

    private const ASSESSMENT_URL = 'https://recaptchaenterprise.googleapis.com/v1/projects/%s/assessments';

    private const TIMEOUT_SECONDS = 5;

    // 設定確認用のトークン（形式が不正なトークンとして扱われ、評価の作成自体が通るかだけを確かめる）
    private const CONFIGURATION_CHECK_TOKEN = 'url-shortener-configuration-check';

    public function __construct(
        private readonly ExternalServiceKeys $keys,
        private readonly ShortenerSettings $settings,
    ) {}

    public function isEnabled(): bool
    {
        return $this->keys->recaptchaSiteKey() !== null
            && $this->keys->recaptchaProjectId() !== null
            && $this->keys->recaptchaApiKey() !== null;
    }

    /** フォームに埋め込むキー ID（サイトキー）。無効なら null */
    public function siteKey(): ?string
    {
        return $this->isEnabled() ? $this->keys->recaptchaSiteKey() : null;
    }

    /** @param  self::ACTION_*  $action */
    public function verify(string $action, ?string $token, ?string $clientIp, ?string $userAgent = null): bool
    {
        $siteKey = $this->keys->recaptchaSiteKey();
        $projectId = $this->keys->recaptchaProjectId();
        $apiKey = $this->keys->recaptchaApiKey();

        if ($siteKey === null || $projectId === null || $apiKey === null) {
            return true;
        }

        if ($token === null || $token === '') {
            Log::notice('reCAPTCHA のトークンが送信されませんでした。');

            return false;
        }

        try {
            $response = $this->createAssessment($projectId, $apiKey, array_filter([
                'token' => $token,
                'siteKey' => $siteKey,
                'expectedAction' => $action,
                'userIpAddress' => $clientIp,
                'userAgent' => $userAgent,
            ]));
        } catch (ConnectionException $e) {
            Log::warning('reCAPTCHA の API に接続できないため、検証せずに続行します。', ['exception' => $e::class]);

            return true;
        }

        if (! $response->successful()) {
            // 設定の誤り（API キー・プロジェクト ID・API の有効化）でも発行は止めない。管理者が気付けるようエラーとして残す
            Log::error('reCAPTCHA の評価を作成できなかったため、検証せずに続行します。', [
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            return true;
        }

        $score = (float) $response->json('riskAnalysis.score', 0);
        $passed = $response->json('tokenProperties.valid') === true
            && $response->json('tokenProperties.action') === $action
            && $score >= $this->settings->recaptchaMinimumScore();

        if (! $passed) {
            Log::notice('reCAPTCHA の判定により発行を拒否しました。', [
                'score' => $score,
                'action' => $response->json('tokenProperties.action'),
                'invalid_reason' => $response->json('tokenProperties.invalidReason'),
                'reasons' => $response->json('riskAnalysis.reasons'),
            ]);
        }

        return $passed;
    }

    /**
     * 管理画面で保存する前に、プロジェクト ID と API キーで評価を作成できるか確かめる。
     * 問題があれば管理者向けのメッセージを返す（Google に接続できず確かめられなかった場合は null）。
     */
    public function configurationError(string $siteKey, string $projectId, string $apiKey): ?string
    {
        try {
            $response = $this->createAssessment($projectId, $apiKey, [
                'token' => self::CONFIGURATION_CHECK_TOKEN,
                'siteKey' => $siteKey,
                'expectedAction' => self::ACTION_SHORTEN,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('reCAPTCHA の設定を確認できませんでした（接続エラー）。', ['exception' => $e::class]);

            return null;
        }

        if ($response->successful()) {
            return null;
        }

        $detail = $response->json('error.message');

        Log::warning('reCAPTCHA の設定の確認で、評価を作成できませんでした。', ['status' => $response->status(), 'error' => $detail]);

        return sprintf(
            'Google Cloud で評価を作成できませんでした（HTTP %d%s）。プロジェクト ID、API キー、reCAPTCHA Enterprise API が有効になっているかを確認してください。',
            $response->status(),
            is_string($detail) && $detail !== '' ? ': '.mb_strimwidth($detail, 0, 200, '…') : '',
        );
    }

    /**
     * @param  array<string, string>  $event
     *
     * @throws ConnectionException
     */
    private function createAssessment(string $projectId, string $apiKey, array $event): Response
    {
        return Http::acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            // API キーは URL に残さないよう、クエリではなくヘッダーで渡す
            ->withHeaders(['X-Goog-Api-Key' => $apiKey])
            ->post(sprintf(self::ASSESSMENT_URL, rawurlencode($projectId)), ['event' => $event]);
    }
}
