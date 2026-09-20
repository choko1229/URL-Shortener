<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\SlugType;
use App\Models\RetiredSlug;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LinkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_statistics(): void
    {
        $user = User::factory()->create();
        $link = ShortUrl::factory()->for($user)->custom('stats-link')->create(['click_count' => 3]);
        $link->clicks()->createMany([
            ['referrer_host' => 'twitter.com', 'country_code' => 'JP', 'device_type' => DeviceType::Mobile, 'clicked_at' => now()->subDay()],
            ['referrer_host' => 'twitter.com', 'country_code' => 'JP', 'device_type' => DeviceType::Desktop, 'clicked_at' => now()],
            ['referrer_host' => null, 'country_code' => null, 'device_type' => DeviceType::Mobile, 'clicked_at' => now()->subDays(40)],
        ]);

        $this->actingAs($user)->get($this->dashboardUrl("/links/{$link->id}"))
            ->assertOk()
            ->assertSee('日別クリック数')
            ->assertSee('twitter.com')
            ->assertSee('直接アクセス・不明')
            ->assertSee('スマートフォン')
            ->assertSee('JP');
    }

    public function test_statistics_of_guest_links_are_visible_only_to_admins(): void
    {
        $guestLink = ShortUrl::factory()->guest()->create();

        $this->actingAs(User::factory()->create())->get($this->dashboardUrl("/links/{$guestLink->id}"))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get($this->dashboardUrl("/links/{$guestLink->id}"))->assertOk()->assertSee('未ログイン');
    }

    public function test_member_cannot_view_other_members_link(): void
    {
        $link = ShortUrl::factory()->create();

        $this->actingAs(User::factory()->create())->get($this->dashboardUrl("/links/{$link->id}"))->assertForbidden();
    }

    public function test_owner_can_delete_link_and_code_stays_retired(): void
    {
        $user = User::factory()->create();
        $link = ShortUrl::factory()->for($user)->custom('bye-link')->create();

        $this->actingAs($user)
            ->from($this->dashboardUrl())
            ->delete($this->dashboardUrl("/links/{$link->id}"))
            ->assertRedirect($this->dashboardUrl())
            ->assertSessionHas('notice');

        $this->assertSoftDeleted($link);
        $this->get($this->mainUrl('/bye-link'))->assertNotFound();

        // 同じコードは発行できない
        $this->actingAs($user)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => 'bye-link', 'expiry' => 'never'])
            ->assertSessionHasErrors('custom_slug');
    }

    public function test_admin_can_delete_any_link(): void
    {
        $link = ShortUrl::factory()->guest()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from($this->dashboardUrl('/admin/links'))
            ->delete($this->dashboardUrl("/links/{$link->id}"))
            ->assertRedirect($this->dashboardUrl('/admin/links'));

        $this->assertSoftDeleted($link);
    }

    public function test_changing_slug_retires_the_old_code(): void
    {
        $user = User::factory()->create();
        $link = ShortUrl::factory()->for($user)->create(['slug' => 'AbC1234', 'slug_type' => SlugType::Random]);

        $this->actingAs($user)
            ->patch($this->dashboardUrl("/links/{$link->id}/slug"), ['custom_slug' => 'my-new-slug'])
            ->assertRedirect($this->dashboardUrl("/links/{$link->id}"))
            ->assertSessionHas('notice');

        $link->refresh();
        $this->assertSame('my-new-slug', $link->slug);
        $this->assertSame(SlugType::Custom, $link->slug_type);
        $this->assertTrue(RetiredSlug::query()->where('slug', 'AbC1234')->exists());

        $this->get($this->mainUrl('/my-new-slug'))->assertOk();
        $this->get($this->mainUrl('/abc1234'))->assertNotFound();

        // 欠番になったコードは、大文字小文字違いも含めてカスタムスラッグにできない
        $other = ShortUrl::factory()->for($user)->create();
        $this->actingAs($user)
            ->from($this->dashboardUrl("/links/{$other->id}"))
            ->patch($this->dashboardUrl("/links/{$other->id}/slug"), ['custom_slug' => 'abc1234'])
            ->assertSessionHasErrors(['custom_slug' => 'このカスタムスラッグはすでに使われています。']);
    }

    public function test_other_members_cannot_change_slug(): void
    {
        $link = ShortUrl::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch($this->dashboardUrl("/links/{$link->id}/slug"), ['custom_slug' => 'hijack'])
            ->assertForbidden();
    }

    public function test_qr_code_can_be_downloaded_as_svg_or_png(): void
    {
        $link = ShortUrl::factory()->custom('qr-target')->create();

        $svg = $this->get($this->mainUrl('/qr-target/qr.svg'));
        $svg->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString("default-src 'none'", (string) $svg->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('attachment; filename=qr-target-qr.svg', (string) $svg->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg', (string) $svg->getContent());

        $png = $this->get($this->mainUrl('/qr-target/qr.png'));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('attachment; filename=qr-target-qr.png', (string) $png->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\x89PNG", (string) $png->getContent());

        // ランダムコードは大文字小文字を無視して照合する
        $this->get($this->mainUrl('/QR-TARGET/qr.svg'))->assertNotFound();
        $this->get($this->mainUrl('/'.$link->slug.'/qr.gif'))->assertNotFound();
        $this->get($this->mainUrl('/missing-code/qr.svg'))->assertNotFound();
    }

    public function test_qr_code_is_not_available_for_deleted_links(): void
    {
        $link = ShortUrl::factory()->custom('gone-soon')->create();
        $link->delete();

        $this->get($this->mainUrl('/gone-soon/qr.svg'))->assertNotFound();
    }

    public function test_dashboard_offers_both_qr_formats(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->custom('my-link')->create();

        $this->actingAs($user)->get($this->dashboardUrl())
            ->assertOk()
            ->assertSee($this->mainUrl('/my-link/qr.svg'))
            ->assertSee($this->mainUrl('/my-link/qr.png'))
            ->assertSee('data-qr-download="svg"', false)
            ->assertSee('data-qr-download="png"', false);
    }

    public function test_guest_can_delete_link_with_deletion_token(): void
    {
        $token = Str::random(40);
        $link = ShortUrl::factory()->guest()->custom('guest-link')->create(['deletion_token_hash' => hash('sha256', $token)]);

        $this->get($this->mainUrl('/delete'))->assertOk()->assertSee('削除用トークン');

        $this->from($this->mainUrl('/delete'))
            ->post($this->mainUrl('/delete'), ['short_url' => 'https://localhost/guest-link', 'deletion_token' => 'wrong-token'])
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($link);

        $this->from($this->mainUrl('/delete'))
            ->post($this->mainUrl('/delete'), ['short_url' => 'localhost/guest-link', 'deletion_token' => $token])
            ->assertRedirect($this->mainUrl('/delete'))
            ->assertSessionHas('notice');
        $this->assertSoftDeleted($link);
    }

    public function test_member_links_cannot_be_deleted_with_token_form(): void
    {
        $link = ShortUrl::factory()->custom('member-link')->create();

        $this->from($this->mainUrl('/delete'))
            ->post($this->mainUrl('/delete'), ['short_url' => 'member-link', 'deletion_token' => 'anything'])
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($link);
    }
}
