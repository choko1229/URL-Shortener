<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ShortUrl;
use App\Models\SitePage;
use App\Models\User;
use App\Support\AccessPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 限定モード: 管理者と許可したユーザーだけが発行・ダッシュボードを使える */
final class RestrictedModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_outsiders_see_the_description_instead_of_the_top_page_and_dashboard(): void
    {
        $this->restrict();

        $this->get($this->mainUrl())
            ->assertOk()
            ->assertSee('このドメインについて')
            // 説明文が未作成でも、何のドメインかは伝える
            ->assertSee('短縮URL専用のドメインです')
            ->assertDontSee('name="original_url"', false)
            ->assertDontSee('使い方');

        // ダッシュボードを開いても、Discord のログインへは案内しない
        $this->get($this->dashboardUrl())
            ->assertOk()
            ->assertSee('このドメインについて');

        // 発行はできない
        $this->post($this->mainUrl('/shorten'), ['original_url' => 'https://example.com/', 'expiry' => '1d'])->assertForbidden();
        $this->assertSame(0, ShortUrl::query()->count());
    }

    public function test_the_description_can_be_written_in_markdown(): void
    {
        $this->restrict();
        SitePage::query()->create([
            'slug' => SitePage::ABOUT,
            'title' => 'このドメインについて',
            'body' => "## 身内用の短縮URLです\n\n**一般公開はしていません。**",
        ]);

        $this->get($this->mainUrl())
            ->assertOk()
            ->assertSee('<h2>身内用の短縮URLです</h2>', false)
            ->assertSee('<strong>一般公開はしていません。</strong>', false)
            ->assertDontSee('短縮URL専用のドメインです');

        // 説明ページはフッターに並べない
        $this->get($this->mainUrl('/contact'))->assertOk()->assertDontSee('href="'.$this->mainUrl('/about').'"', false);
    }

    public function test_outsiders_can_be_sent_to_another_site(): void
    {
        $this->restrict(action: 'redirect', url: 'https://example.org/portfolio');

        $this->get($this->mainUrl())->assertRedirect('https://example.org/portfolio');
        $this->get($this->dashboardUrl())->assertRedirect('https://example.org/portfolio');
    }

    public function test_short_links_and_public_pages_keep_working(): void
    {
        $this->restrict();
        ShortUrl::factory()->custom('shared')->create(['original_url' => 'https://example.com/shared']);

        $this->get($this->mainUrl('/shared'))->assertOk()->assertSee('name="ticket"', false);
        $this->get($this->mainUrl('/shared/qr.svg'))->assertOk();
        $this->get($this->mainUrl('/contact'))->assertOk();
        $this->get($this->mainUrl('/delete'))->assertOk();
        // 許可された人がログインできるよう、ログインの入口は開けておく
        $this->get($this->dashboardUrl('/login'))->assertRedirect();
    }

    public function test_admins_and_permitted_users_can_use_the_site(): void
    {
        $this->restrict();
        $admin = User::factory()->admin()->create();
        $permitted = User::factory()->create(['restricted_access' => true]);

        foreach ([$admin, $permitted] as $user) {
            $this->actingAs($user)->get($this->mainUrl())->assertOk()->assertSee('name="original_url"', false);
            $this->actingAs($user)->get($this->dashboardUrl())->assertOk()->assertSee('すぐに短縮URLを発行');
        }

        $this->actingAs($permitted)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'expiry' => 'never'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ShortUrl::query()->count());
    }

    public function test_users_who_are_not_permitted_are_logged_out(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member);
        $this->restrict();

        $this->get($this->dashboardUrl())
            ->assertOk()
            ->assertSee('このサービスは限定公開のため、ログアウトしました');
        $this->assertGuest();
    }

    public function test_discord_login_is_refused_for_users_who_are_not_permitted(): void
    {
        User::factory()->admin()->create();
        AppSetting::store(AppSetting::DISCORD_CLIENT_ID, '123456789012345678');
        AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, 'discord-client-secret-value', encrypt: true);
        $this->restrict();

        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'access-token', 'token_type' => 'Bearer']),
            'discord.com/api/users/@me' => Http::response(['id' => '444444444444444444', 'username' => 'stranger', 'global_name' => null, 'avatar' => null]),
        ]);

        $this->withSession(['discord_oauth_state' => 'state-value'])
            ->get($this->dashboardUrl('/login/callback?state=state-value&code=authorization-code'))
            ->assertRedirect(route('main.home'))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '限定公開'));

        $this->assertGuest();
        // 一覧には載るため、管理者があとから許可できる
        $stranger = User::query()->where('discord_id', '444444444444444444')->sole();
        $this->assertFalse($stranger->restricted_access);

        // 断った理由は説明ページに表示される
        $this->get($this->mainUrl())->assertSee('利用が許可されていません');
    }

    public function test_admin_can_switch_the_mode_and_choose_the_behaviour(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/site'))->assertOk()->assertSee('公開範囲');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->put($this->dashboardUrl('/admin/site/access'), [
                'mode' => 'restricted',
                'outsider_action' => 'redirect',
                'redirect_url' => 'https://example.org/',
            ])
            ->assertRedirect($this->dashboardUrl('/admin/site'))
            ->assertSessionHas('notice');

        $policy = $this->app->make(AccessPolicy::class);
        $policy->save(['mode' => 'restricted', 'outsider_action' => 'redirect', 'redirect_url' => 'https://example.org/']);
        $this->assertTrue($policy->isRestricted());
        $this->assertSame('https://example.org/', $policy->redirectUrl());

        // 管理者自身は締め出されない
        $this->actingAs($admin)->get($this->dashboardUrl('/admin/site'))->assertOk();
    }

    public function test_invalid_access_settings_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        // 移動先が空、または自分のサイト（移動を繰り返してしまう）
        foreach (['', $this->mainUrl('/'), 'javascript:alert(1)'] as $url) {
            $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
                ->put($this->dashboardUrl('/admin/site/access'), ['mode' => 'restricted', 'outsider_action' => 'redirect', 'redirect_url' => $url])
                ->assertSessionHasErrors('redirect_url');
        }

        $this->assertNull(AppSetting::valueFor(AppSetting::ACCESS_MODE));
    }

    public function test_admin_can_permit_and_revoke_users(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/users'))->assertOk()->assertSee('限定モードでの利用');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/users'))
            ->patch($this->dashboardUrl('/admin/users/'.$member->id.'/access'), ['allowed' => '1'])
            ->assertSessionHas('notice');
        $this->assertTrue($member->refresh()->restricted_access);

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/users'))
            ->patch($this->dashboardUrl('/admin/users/'.$member->id.'/access'), ['allowed' => '0']);
        $this->assertFalse($member->refresh()->restricted_access);
    }

    public function test_members_cannot_change_the_mode_or_permissions(): void
    {
        User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($member)->put($this->dashboardUrl('/admin/site/access'), ['mode' => 'restricted', 'outsider_action' => 'page'])->assertForbidden();
        $this->actingAs($member)->patch($this->dashboardUrl('/admin/users/'.$member->id.'/access'), ['allowed' => '1'])->assertForbidden();

        $this->assertNull(AppSetting::valueFor(AppSetting::ACCESS_MODE));
        $this->assertFalse($member->refresh()->restricted_access);
    }

    public function test_public_mode_is_unchanged(): void
    {
        $this->get($this->mainUrl())->assertOk()->assertSee('name="original_url"', false);
        $this->get($this->dashboardUrl())->assertRedirect(route('auth.login'));
    }

    private function restrict(string $action = 'page', ?string $url = null): void
    {
        $this->app->make(AccessPolicy::class)->save(['mode' => 'restricted', 'outsider_action' => $action, 'redirect_url' => $url]);
    }
}
