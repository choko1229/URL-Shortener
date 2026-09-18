<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\ExternalServiceKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Discord ログインの初回設定と、管理画面「外部サービス」 */
final class ServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '123456789012345678';

    private const CLIENT_SECRET = 'discord_client_secret_value_0123';

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

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/services'))
            ->assertOk()
            ->assertSee(self::CLIENT_ID)
            ->assertDontSee(self::CLIENT_SECRET)
            ->assertSee($this->dashboardUrl('/login/callback'));

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/services'))->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'safe_browsing_api_key' => 'AIzaSy-safe-browsing-key_0123',
            'recaptcha_site_key' => '6Lc-site-key-abcdefghijklmn',
            'recaptcha_secret_key' => '6Lc-secret-key-abcdefghijklmn',
        ])->assertRedirect($this->dashboardUrl('/admin/services'))->assertSessionHas('notice');

        // 空欄の Secret は変更しない
        $this->assertSame(self::CLIENT_SECRET, AppSetting::valueFor(AppSetting::DISCORD_CLIENT_SECRET));
        $this->assertSame('AIzaSy-safe-browsing-key_0123', AppSetting::valueFor(AppSetting::SAFE_BROWSING_API_KEY));
        $this->assertTrue(AppSetting::query()->where('key', AppSetting::SAFE_BROWSING_API_KEY)->value('is_encrypted'));
        $this->assertSame('6Lc-site-key-abcdefghijklmn', AppSetting::valueFor(AppSetting::RECAPTCHA_SITE_KEY));
        $this->assertFalse(AppSetting::query()->where('key', AppSetting::RECAPTCHA_SITE_KEY)->value('is_encrypted'));
        $this->assertTrue(AppSetting::query()->where('key', AppSetting::RECAPTCHA_SECRET_KEY)->value('is_encrypted'));

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'clear_safe_browsing_api_key' => '1',
            'clear_recaptcha' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull(AppSetting::valueFor(AppSetting::SAFE_BROWSING_API_KEY));
        $this->assertNull(AppSetting::valueFor(AppSetting::RECAPTCHA_SITE_KEY));
        $this->assertNull(AppSetting::valueFor(AppSetting::RECAPTCHA_SECRET_KEY));
    }

    public function test_recaptcha_keys_must_be_set_as_a_pair(): void
    {
        $admin = User::factory()->admin()->create();
        AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, self::CLIENT_SECRET, encrypt: true);

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'recaptcha_site_key' => '6Lc-site-key-abcdefghijklmn',
        ])->assertSessionHasErrors('recaptcha_secret_key');

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/services'), [
            'discord_client_id' => self::CLIENT_ID,
            'recaptcha_secret_key' => '6Lc-secret-key-abcdefghijklmn',
        ])->assertSessionHasErrors('recaptcha_site_key');

        $this->assertNull(AppSetting::valueFor(AppSetting::RECAPTCHA_SITE_KEY));
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
