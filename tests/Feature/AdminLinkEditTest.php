<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ShortUrl;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 管理者による短縮URLの編集（元URL・有効期限・発行者） */
final class AdminLinkEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_list_links_to_the_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $link = ShortUrl::factory()->custom('editable')->create();

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/links'))
            ->assertOk()
            ->assertSee(route('dashboard.links.show', ['shortUrl' => $link->id]).'#admin-edit', false);

        $this->actingAs($admin)->get($this->dashboardUrl('/links/'.$link->id))
            ->assertOk()
            ->assertSee('管理者による編集')
            ->assertSee('name="original_url"', false);
    }

    public function test_admin_can_change_the_destination_expiry_and_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        // 未ログインで発行したもの（削除用トークンと IP のハッシュを持つ）
        $link = ShortUrl::factory()->custom('moved')->create([
            'user_id' => null,
            'original_url' => 'https://example.com/old',
            'expires_at' => null,
            'deletion_token_hash' => hash('sha256', 'token'),
            'creator_ip_hash' => 'ip-hash',
        ]);

        $this->actingAs($admin)
            ->patch($this->dashboardUrl('/admin/links/'.$link->id), [
                'original_url' => 'https://example.com/new',
                'expiry' => 'custom',
                'expires_at' => '2030-01-02T03:04',
                'user_id' => (string) $member->id,
            ])
            ->assertRedirect(route('dashboard.links.show', ['shortUrl' => $link->id]).'#admin-edit')
            ->assertSessionHas('notice', '元URL・有効期限・発行者を変更しました。');

        $link->refresh();
        $this->assertSame('moved', $link->slug);
        $this->assertSame('https://example.com/new', $link->original_url);
        // 表示タイムゾーン（日本時間）で入力した日時を UTC で保存する
        $this->assertSame('2030-01-01 18:04', $link->expires_at?->utc()->format('Y-m-d H:i'));
        $this->assertSame($member->id, $link->user_id);
        // ログインユーザーのものにしたら、未ログイン発行の名残は消す
        $this->assertNull($link->deletion_token_hash);
        $this->assertNull($link->creator_ip_hash);

        // 移した先のユーザーのダッシュボードに出る
        $this->actingAs($member)->get($this->dashboardUrl())->assertSee('localhost/moved');
    }

    public function test_links_can_be_made_permanent_or_returned_to_guest(): void
    {
        $admin = User::factory()->admin()->create();
        $link = ShortUrl::factory()->for(User::factory())->create(['expires_at' => CarbonImmutable::now()->addDay()]);

        $this->actingAs($admin)->patch($this->dashboardUrl('/admin/links/'.$link->id), [
            'original_url' => $link->original_url,
            'expiry' => 'never',
            'expires_at' => '2030-01-02T03:04',
            'user_id' => '',
        ])->assertSessionHasNoErrors();

        $link->refresh();
        $this->assertNull($link->expires_at);
        $this->assertNull($link->user_id);
    }

    public function test_a_past_date_expires_the_link_without_deleting_it(): void
    {
        $admin = User::factory()->admin()->create();
        $link = ShortUrl::factory()->custom('ended')->create(['expires_at' => null]);

        $this->actingAs($admin)->patch($this->dashboardUrl('/admin/links/'.$link->id), [
            'original_url' => $link->original_url,
            'expiry' => 'custom',
            'expires_at' => '2020-01-01T00:00',
            'user_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($link->refresh()->trashed());
        $this->get($this->mainUrl('/ended'))->assertGone();
    }

    public function test_saving_without_changes_keeps_the_exact_expiry(): void
    {
        $admin = User::factory()->admin()->create();
        // 秒まで持つ有効期限（画面は分単位）
        $expiresAt = CarbonImmutable::parse('2031-05-06 07:08:09', 'UTC');
        $link = ShortUrl::factory()->create(['expires_at' => $expiresAt, 'user_id' => null]);

        $this->actingAs($admin)->patch($this->dashboardUrl('/admin/links/'.$link->id), [
            'original_url' => $link->original_url,
            'expiry' => 'custom',
            'expires_at' => $expiresAt->setTimezone('Asia/Tokyo')->format('Y-m-d\TH:i'),
            'user_id' => '',
        ])->assertSessionHas('notice', '変更はありませんでした。');

        $this->assertSame('2031-05-06 07:08:09', $link->refresh()->expires_at?->utc()->format('Y-m-d H:i:s'));
    }

    public function test_invalid_changes_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $link = ShortUrl::factory()->create(['original_url' => 'https://example.com/keep']);

        $this->actingAs($admin)->from($this->dashboardUrl('/links/'.$link->id))
            ->patch($this->dashboardUrl('/admin/links/'.$link->id), [
                'original_url' => $this->mainUrl('/loop'),
                'expiry' => 'custom',
                'expires_at' => 'tomorrow',
                'user_id' => '999999',
            ])
            ->assertSessionHasErrors(['original_url', 'expires_at', 'user_id']);

        $this->assertSame('https://example.com/keep', $link->refresh()->original_url);
    }

    public function test_members_cannot_use_the_admin_edit(): void
    {
        User::factory()->admin()->create();
        $member = User::factory()->create();
        $link = ShortUrl::factory()->for($member)->create(['original_url' => 'https://example.com/mine']);

        // 自分のリンクでも、元URL・有効期限・発行者は変えられない
        $this->actingAs($member)->get($this->dashboardUrl('/links/'.$link->id))->assertOk()->assertDontSee('管理者による編集');
        $this->actingAs($member)->patch($this->dashboardUrl('/admin/links/'.$link->id), [
            'original_url' => 'https://example.com/changed',
            'expiry' => 'never',
        ])->assertForbidden();

        $this->assertSame('https://example.com/mine', $link->refresh()->original_url);
    }
}
