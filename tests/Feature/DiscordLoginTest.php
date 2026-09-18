<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\ExternalServiceKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class DiscordLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::store(AppSetting::DISCORD_CLIENT_ID, '123456789012345678');
        AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, 'discord-client-secret-value', encrypt: true);
    }

    public function test_login_redirects_to_discord_with_identify_scope_and_state(): void
    {
        $response = $this->get($this->dashboardUrl('/login'));

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://discord.com/oauth2/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('identify', $query['scope']);
        $this->assertSame('123456789012345678', $query['client_id']);
        $this->assertSame($this->dashboardUrl('/login/callback'), $query['redirect_uri']);
        $this->assertSame(session('discord_oauth_state'), $query['state']);
        $this->assertStringNotContainsString('discord-client-secret-value', $location);
    }

    public function test_first_user_after_setup_becomes_admin_and_later_users_are_members(): void
    {
        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'access-token', 'token_type' => 'Bearer']),
            'discord.com/api/users/@me' => Http::sequence()
                ->push(['id' => '111111111111111111', 'username' => 'first_user', 'global_name' => 'はじめ', 'avatar' => null])
                ->push(['id' => '222222222222222222', 'username' => 'second_user', 'global_name' => null, 'avatar' => null]),
        ]);

        $this->loginViaCallback()->assertRedirect(route('dashboard.home'));

        $first = User::query()->where('discord_id', '111111111111111111')->sole();
        $this->assertSame(UserRole::Admin, $first->role);
        $this->assertSame('はじめ', $first->global_name);
        $this->assertAuthenticatedAs($first);

        $this->post($this->dashboardUrl('/logout'));

        $this->loginViaCallback()->assertRedirect(route('dashboard.home'));

        $this->assertSame(UserRole::Member, User::query()->where('discord_id', '222222222222222222')->sole()->role);
    }

    public function test_existing_user_profile_is_updated_on_login(): void
    {
        User::factory()->admin()->create();
        $user = User::factory()->create(['discord_id' => '333333333333333333', 'username' => 'old_name']);

        $this->fakeDiscord('333333333333333333', 'new_name', '新しい表示名');
        $this->loginViaCallback();

        $user->refresh();
        $this->assertSame('new_name', $user->username);
        $this->assertSame('新しい表示名', $user->global_name);
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame(2, User::query()->count());
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $this->withSession(['discord_oauth_state' => 'expected-state'])
            ->get($this->dashboardUrl('/login/callback?state=wrong&code=abc'))
            ->assertRedirect(route('main.home'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_callback_shows_error_when_token_exchange_fails(): void
    {
        Http::fake(['discord.com/api/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->withSession(['discord_oauth_state' => 'state-value'])
            ->get($this->dashboardUrl('/login/callback?state=state-value&code=abc'))
            ->assertRedirect(route('main.home'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_login_is_unavailable_until_discord_is_configured(): void
    {
        AppSetting::query()->where('key', AppSetting::DISCORD_CLIENT_SECRET)->delete();
        app(ExternalServiceKeys::class)->forget();

        $this->get($this->dashboardUrl('/login'))
            ->assertRedirect(route('main.home'))
            ->assertSessionHas('error');
    }

    private function fakeDiscord(string $id, string $username, ?string $globalName): void
    {
        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'access-token', 'token_type' => 'Bearer']),
            'discord.com/api/users/@me' => Http::response([
                'id' => $id,
                'username' => $username,
                'global_name' => $globalName,
                'avatar' => 'a_0123456789abcdef0123456789abcdef',
            ]),
        ]);
    }

    private function loginViaCallback(): TestResponse
    {
        return $this->withSession(['discord_oauth_state' => 'state-value'])
            ->get($this->dashboardUrl('/login/callback?state=state-value&code=authorization-code'));
    }
}
