<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\ExternalServiceKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Discord ログインの初回設定と、管理画面「外部サービス」 */
final class ServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '123456789012345678';

    private const CLIENT_SECRET = 'discord_client_secret_value_0123';

    private const RECAPTCHA_INPUT = [
        'recaptcha_site_key' => '6Lc-site-key-abcdefghijklmn',
        'recaptcha_project_id' => 'url-shortener-test',
        'recaptcha_api_key' => 'AIzaSy-recaptcha-api-key_0123',
    ];

    // 設定確認用のトークンは形式が不正なため、評価自体は作成でき（HTTP 200）、トークンは無効と判定される
    private const INVALID_TOKEN_ASSESSMENT = [
        'tokenProperties' => ['valid' => false, 'invalidReason' => 'MALFORMED'],
        'riskAnalysis' => ['score' => 0],
    ];

    public function test_first_run_setup_shows_redirect_uri(): void
    {
        $this->get($this->dashboardUrl('/login/setup'))
            ->assertOk()
            ->assertSee($this->dashboardUrl('/login/callback'))
            ->assertSee('name="discord_client_secret"', false);
    }

    public function test_first_run_setup_saves_encrypted_secret_and_starts_login(): void
    {
        $this->post($this->dashboardUrl('/login/setup'), [
            'discord_client_id' => self::CLIENT_ID,
            'discord_client_secret' => self::CLIENT_SECRET,
        ])->assertRedirect(route('auth.login'));

        $this->assertSame(self::CLIENT_ID, AppSetting::valueFor(AppSetting::DISCORD_CLIENT_ID));
        $this->assertSame(self::CLIENT_SECRET, AppSetting::valueFor(AppSetting::DISCORD_CLIENT_SECRET));
        $this->assertTrue(AppSetting::query()->where('key', AppSetting::DISCORD_CLIENT_SECRET)->value('is_encrypted'));
        $this->assertStringNotContainsString(
            self::CLIENT_SECRET,
            (string) AppSetting::query()->where('key', AppSetting::DISCORD_CLIENT_SECRET)->value('value'),
        );

        // 保存後はそのまま Discord の認可画面へ進める
        $this->assertStringStartsWith('https://discord.com/oauth2/authorize?', (string) $this->get($this->dashboardUrl('/login'))->headers->get('Location'));
    }

    public function test_first_run_setup_requires_secret_only_until_it_is_stored(): void
    {
        $this->post($this->dashboardUrl('/login/setup'), ['discord_client_id' => self::CLIENT_ID])
            ->assertSessionHasErrors('discord_client_secret');

        AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, self::CLIENT_SECRET, encrypt: true);
        $this->app->make(ExternalServiceKeys::class)->forget();

        // Client ID だけ直す場合は、Secret を空欄のままにできる
        $this->post($this->dashboardUrl('/login/setup'), ['discord_client_id' => '987654321098765432'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('auth.login'));

        $this->assertSame('987654321098765432', AppSetting::valueFor(AppSetting::DISCORD_CLIENT_ID));
        $this->assertSame(self::CLIENT_SECRET, AppSetting::valueFor(AppSetting::DISCORD_CLIENT_SECRET));
    }

    public function test_first_run_setup_rejects_invalid_values(): void
    {
        $this->post($this->dashboardUrl('/login/setup'), [
            'discord_client_id' => 'not-a-number',
            'discord_client_secret' => "secret\nwith-newline",
        ])->assertSessionHasErrors(['discord_client_id', 'discord_client_secret']);

        $this->assertNull(AppSetting::valueFor(AppSetting::DISCORD_CLIENT_ID));
    }

    public function test_first_run_setup_is_closed_once_an_admin_exists(): void
    {
        User::factory()->admin()->create();

        $this->get($this->dashboardUrl('/login/setup'))->assertNotFound();
        $this->post($this->dashboardUrl('/login/setup'), [
            'discord_client_id' => self::CLIENT_ID,
            'discord_client_secret' => self::CLIENT_SECRET,
        ])->assertNotFound();

        $this->assertNull(AppSetting::valueFor(AppSetting::DISCORD_CLIENT_ID));
    }

    public function test_admin_can_update_service_keys_without_exposing_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        AppSetting::store(AppSetting::DISCORD_CLIENT_ID, self::CLIENT_ID);
        AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, self::CLIENT_SECRET, encrypt: true);
        Http::fake(['recaptchaenterprise.googleapis.com/*' => Http::response(self::INVALID_TOKEN_ASSESSMENT)]);

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/services'))
            ->assertOk()
            ->assertSee(self::CLIENT_ID)
            ->assertDontSee(self::CLIENT_SECRET)
            ->assertSee($this->dashboardUrl('/login/callback'));

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/services'))->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'safe_browsing_api_key' => 'AIzaSy-safe-browsing-key_0123',
            ...self::RECAPTCHA_INPUT,
        ])->assertRedirect($this->dashboardUrl('/admin/services'))->assertSessionHas('notice');

        // 空欄の Secret は変更しない
        $this->assertSame(self::CLIENT_SECRET, AppSetting::valueFor(AppSetting::DISCORD_CLIENT_SECRET));
        $this->assertSame('AIzaSy-safe-browsing-key_0123', AppSetting::valueFor(AppSetting::SAFE_BROWSING_API_KEY));
        $this->assertTrue(AppSetting::query()->where('key', AppSetting::SAFE_BROWSING_API_KEY)->value('is_encrypted'));
        $this->assertSame('6Lc-site-key-abcdefghijklmn', AppSetting::valueFor(AppSetting::RECAPTCHA_SITE_KEY));
        $this->assertFalse(AppSetting::query()->where('key', AppSetting::RECAPTCHA_SITE_KEY)->value('is_encrypted'));
        $this->assertSame('url-shortener-test', AppSetting::valueFor(AppSetting::RECAPTCHA_PROJECT_ID));
        $this->assertSame('AIzaSy-recaptcha-api-key_0123', AppSetting::valueFor(AppSetting::RECAPTCHA_API_KEY));
        $this->assertTrue(AppSetting::query()->where('key', AppSetting::RECAPTCHA_API_KEY)->value('is_encrypted'));

        // 保存前に、入力されたプロジェクトと API キーで評価を作成できるか確かめている
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/v1/projects/url-shortener-test/assessments')
            && $request->hasHeader('X-Goog-Api-Key', 'AIzaSy-recaptcha-api-key_0123')
            && $request['event']['siteKey'] === '6Lc-site-key-abcdefghijklmn');

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/services'))
            ->assertSee('value="url-shortener-test"', false)
            ->assertDontSee('AIzaSy-recaptcha-api-key_0123');

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'clear_safe_browsing_api_key' => '1',
            'clear_recaptcha' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull(AppSetting::valueFor(AppSetting::SAFE_BROWSING_API_KEY));
        $this->assertNull(AppSetting::valueFor(AppSetting::RECAPTCHA_SITE_KEY));
        $this->assertNull(AppSetting::valueFor(AppSetting::RECAPTCHA_PROJECT_ID));
        $this->assertNull(AppSetting::valueFor(AppSetting::RECAPTCHA_API_KEY));
    }

    public function test_recaptcha_needs_key_id_project_and_api_key_together(): void
    {
        $admin = User::factory()->admin()->create();
        AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, self::CLIENT_SECRET, encrypt: true);
        Http::fake();

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'recaptcha_site_key' => '6Lc-site-key-abcdefghijklmn',
        ])->assertSessionHasErrors(['recaptcha_project_id', 'recaptcha_api_key']);

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'recaptcha_project_id' => 'Not_A_Project',
            'recaptcha_api_key' => 'AIzaSy-recaptcha-api-key_0123',
        ])->assertSessionHasErrors('recaptcha_project_id');

        $this->assertNull(AppSetting::valueFor(AppSetting::RECAPTCHA_SITE_KEY));
        Http::assertNothingSent();
    }

    public function test_recaptcha_settings_are_rejected_when_google_refuses_the_api_key(): void
    {
        $admin = User::factory()->admin()->create();
        AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, self::CLIENT_SECRET, encrypt: true);
        Http::fake(['recaptchaenterprise.googleapis.com/*' => Http::response([
            'error' => ['code' => 403, 'message' => 'reCAPTCHA Enterprise API has not been used in project 123 before or it is disabled.', 'status' => 'PERMISSION_DENIED'],
        ], 403)]);

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            ...self::RECAPTCHA_INPUT,
        ])->assertSessionHasErrors('recaptcha_api_key');

        $this->assertStringContainsString('HTTP 403', (string) session('errors')->first('recaptcha_api_key'));
        $this->assertNull(AppSetting::valueFor(AppSetting::RECAPTCHA_API_KEY));
    }

    public function test_recaptcha_settings_are_saved_when_google_cannot_be_reached(): void
    {
        $admin = User::factory()->admin()->create();
        AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, self::CLIENT_SECRET, encrypt: true);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            ...self::RECAPTCHA_INPUT,
        ])->assertSessionHasNoErrors();

        $this->assertSame('url-shortener-test', AppSetting::valueFor(AppSetting::RECAPTCHA_PROJECT_ID));
    }

    public function test_members_cannot_update_service_keys(): void
    {
        User::factory()->admin()->create();

        $this->actingAs(User::factory()->create())->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'discord_client_secret' => self::CLIENT_SECRET,
        ])->assertForbidden();

        $this->assertNull(AppSetting::valueFor(AppSetting::DISCORD_CLIENT_ID));
    }
}
