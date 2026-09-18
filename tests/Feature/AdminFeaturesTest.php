<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReservedWordCategory;
use App\Enums\UserRole;
use App\Models\ApiKey;
use App\Models\ReservedWord;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_open_admin_pages(): void
    {
        $member = User::factory()->create();

        foreach (['/admin/links', '/admin/users', '/admin/reserved-words', '/admin/api-keys', '/admin/services', '/admin/updates'] as $path) {
            $this->actingAs($member)->get($this->dashboardUrl($path))->assertForbidden();
        }
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['/admin/links', '/admin/users', '/admin/reserved-words', '/admin/api-keys', '/admin/services', '/admin/updates', '/settings'] as $path) {
            $this->actingAs($admin)->get($this->dashboardUrl($path))->assertOk();
        }
    }

    public function test_admin_sees_all_links_including_guest_links_and_can_filter(): void
    {
        $member = User::factory()->create(['global_name' => 'メンバー']);
        ShortUrl::factory()->for($member)->custom('member-link')->create();
        ShortUrl::factory()->guest()->custom('guest-link')->create();
        ShortUrl::factory()->custom('deleted-link')->create()->delete();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/links'))
            ->assertOk()
            ->assertSee('localhost/member-link')
            ->assertSee('localhost/guest-link')
            ->assertSee('未ログイン')
            ->assertSee('メンバー')
            ->assertDontSee('localhost/deleted-link');

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/links?owner=guest'))
            ->assertSee('localhost/guest-link')
            ->assertDontSee('localhost/member-link');

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/links?state=deleted'))
            ->assertSee('localhost/deleted-link')
            ->assertSee('削除済み');
    }

    public function test_admin_can_promote_and_demote_but_not_remove_last_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/users'))
            ->patch($this->dashboardUrl("/admin/users/{$member->id}/role"), ['role' => 'admin'])
            ->assertSessionHas('notice');
        $this->assertSame(UserRole::Admin, $member->refresh()->role);

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/users'))
            ->patch($this->dashboardUrl("/admin/users/{$member->id}/role"), ['role' => 'member'])
            ->assertSessionHas('notice');
        $this->assertSame(UserRole::Member, $member->refresh()->role);

        // 最後の管理者は解除できない
        $this->actingAs($admin)->from($this->dashboardUrl('/admin/users'))
            ->patch($this->dashboardUrl("/admin/users/{$admin->id}/role"), ['role' => 'member'])
            ->assertSessionHas('error');
        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_admin_can_add_and_remove_reserved_words(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/reserved-words'))
            ->post($this->dashboardUrl('/admin/reserved-words'), ['word' => 'Special', 'category' => 'custom'])
            ->assertSessionHas('notice');

        $word = ReservedWord::query()->sole();
        $this->assertSame('special', $word->word);
        $this->assertSame(ReservedWordCategory::Custom, $word->category);
        $this->assertSame($admin->id, $word->created_by_user_id);

        // 追加した予約語はメンバーのカスタムスラッグに使えない
        $this->actingAs(User::factory()->create())->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => 'SPECIAL', 'expiry' => 'never'])
            ->assertSessionHasErrors('custom_slug');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/reserved-words'))
            ->post($this->dashboardUrl('/admin/reserved-words'), ['word' => 'special', 'category' => 'custom'])
            ->assertSessionHasErrors('word');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/reserved-words'))
            ->delete($this->dashboardUrl("/admin/reserved-words/{$word->id}"))
            ->assertSessionHas('notice');
        $this->assertSame(0, ReservedWord::query()->count());
    }

    public function test_admin_can_issue_and_revoke_api_keys(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/api-keys'))
            ->post($this->dashboardUrl('/admin/api-keys'), ['name' => 'BOOTH まとめサイト'])
            ->assertSessionHas('new_api_token');

        $token = (string) session('new_api_token');
        $key = ApiKey::query()->sole();
        $this->assertStringStartsWith('chok_', $token);
        $this->assertSame(hash('sha256', $token), $key->key_hash);
        $this->assertStringNotContainsString($token, (string) json_encode($key->getAttributes()));

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/api-keys'))->assertSee($token);
        $this->actingAs($admin)->get($this->dashboardUrl('/admin/api-keys'))->assertDontSee($token);

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/api-keys'))
            ->delete($this->dashboardUrl("/admin/api-keys/{$key->id}"))
            ->assertSessionHas('notice');
        $this->assertTrue($key->refresh()->isRevoked());
    }

    public function test_member_can_withdraw_and_optionally_delete_links(): void
    {
        User::factory()->admin()->create();
        $member = User::factory()->create(['discord_id' => '444444444444444444']);
        $link = ShortUrl::factory()->for($member)->create();

        $this->actingAs($member)->from($this->dashboardUrl('/settings'))
            ->delete($this->dashboardUrl('/account'), ['confirm' => '1', 'delete_links' => '1'])
            ->assertRedirect(route('main.home'));

        $this->assertGuest();
        $this->assertSoftDeleted($member);
        $member->refresh();
        $this->assertNotSame('444444444444444444', $member->discord_id);
        $this->assertSame('退会済みユーザー', $member->username);
        $this->assertSoftDeleted($link);
    }

    public function test_withdrawal_keeps_links_by_default_and_last_admin_cannot_withdraw(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $link = ShortUrl::factory()->for($member)->create();

        $this->actingAs($member)->delete($this->dashboardUrl('/account'), ['confirm' => '1']);
        $this->assertNotSoftDeleted($link);

        $this->actingAs($admin)->from($this->dashboardUrl('/settings'))
            ->delete($this->dashboardUrl('/account'), ['confirm' => '1'])
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($admin);
    }
}
