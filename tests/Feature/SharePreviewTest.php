<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PreviewMode;
use App\Models\AppSetting;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Discord・X などに貼ったときのカード（OGP）の出し方 */
final class SharePreviewTest extends TestCase
{
    use RefreshDatabase;

    private const DISCORD_BOT = 'Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)';

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    public function test_default_lets_the_destination_card_through(): void
    {
        ShortUrl::factory()->custom('to-blog')->create(['original_url' => 'https://example.com/blog/1']);

        // クローラーは転送先へ通す（転送先のカードが表示される）
        $this->withHeader('User-Agent', self::DISCORD_BOT)
            ->get($this->mainUrl('/to-blog'))
            ->assertRedirect('https://example.com/blog/1');

        // 人が開いたときはこれまでどおり中間ページ
        $this->withHeader('User-Agent', self::BROWSER)
            ->get($this->mainUrl('/to-blog'))
            ->assertOk()
            ->assertSee('name="ticket"', false);
    }

    public function test_hidden_card_shows_the_service_name_only(): void
    {
        AppSetting::store(AppSetting::SITE_NAME, 'みじかいURL');
        ShortUrl::factory()->custom('secret-link')->create([
            'original_url' => 'https://example.com/private/report',
            'preview_mode' => PreviewMode::Service,
        ]);

        $response = $this->withHeader('User-Agent', self::DISCORD_BOT)->get($this->mainUrl('/secret-link'));

        $response->assertOk()
            ->assertSee('<meta property="og:title" content="みじかいURL">', false)
            // 短縮URLの表示は SHORTENER_SHORT_URL_BASE に従う
            ->assertSee('<meta property="og:url" content="'.config('shortener.short_url_base').'/secret-link">', false)
            ->assertDontSee('example.com');
    }

    public function test_custom_card_shows_the_given_content(): void
    {
        ShortUrl::factory()->custom('event')->create([
            'preview_mode' => PreviewMode::Custom,
            'preview_title' => '秋のイベント',
            'preview_description' => '10月1日に開催します',
            'preview_image_url' => 'https://images.example.com/event.png',
        ]);

        $this->withHeader('User-Agent', self::DISCORD_BOT)
            ->get($this->mainUrl('/event'))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="秋のイベント">', false)
            ->assertSee('<meta property="og:description" content="10月1日に開催します">', false)
            ->assertSee('<meta property="og:image" content="https://images.example.com/event.png">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_password_protected_links_never_leak_the_destination(): void
    {
        ShortUrl::factory()->custom('locked')->create([
            'original_url' => 'https://example.com/members-only',
            'password_hash' => Hash::make('secret123'),
            // 「転送先のカードを見せる」設定でも、パスワード付きなら隠す
            'preview_mode' => PreviewMode::Destination,
        ]);

        $response = $this->withHeader('User-Agent', self::DISCORD_BOT)->get($this->mainUrl('/locked'));

        $response->assertOk()->assertDontSee('example.com');
    }

    public function test_guest_can_choose_the_card_when_issuing(): void
    {
        $this->get($this->mainUrl())->assertOk()->assertSee('共有時のカード');

        $this->from($this->mainUrl())->post($this->mainUrl('/shorten'), [
            'original_url' => 'https://example.com/',
            'expiry' => '1d',
            'preview_mode' => PreviewMode::Custom->value,
            'preview_title' => 'お知らせ',
            'preview_image_url' => 'https://images.example.com/a.png',
        ])->assertSessionHasNoErrors();

        $link = ShortUrl::query()->sole();
        $this->assertSame(PreviewMode::Custom, $link->preview_mode);
        $this->assertSame('お知らせ', $link->preview_title);
        $this->assertSame('https://images.example.com/a.png', $link->preview_image_url);
    }

    public function test_custom_card_requires_a_title(): void
    {
        $this->from($this->mainUrl())->post($this->mainUrl('/shorten'), [
            'original_url' => 'https://example.com/',
            'expiry' => '1d',
            'preview_mode' => PreviewMode::Custom->value,
            'preview_image_url' => 'not-a-url',
        ])->assertSessionHasErrors(['preview_title', 'preview_image_url']);

        $this->assertSame(0, ShortUrl::query()->count());
    }

    public function test_owner_can_change_the_card_afterwards(): void
    {
        $user = User::factory()->create();
        $link = ShortUrl::factory()->for($user)->custom('my-link')->create();

        $this->actingAs($user)->get($this->dashboardUrl('/links/'.$link->id))
            ->assertOk()
            ->assertSee('共有時のカード');

        $this->actingAs($user)->from($this->dashboardUrl('/links/'.$link->id))
            ->patch($this->dashboardUrl('/links/'.$link->id.'/preview'), [
                'preview_mode' => PreviewMode::Custom->value,
                'preview_title' => '資料',
                'preview_description' => '社内向け',
            ])
            ->assertRedirect($this->dashboardUrl('/links/'.$link->id))
            ->assertSessionHas('notice');

        $link->refresh();
        $this->assertSame(PreviewMode::Custom, $link->preview_mode);
        $this->assertSame('資料', $link->preview_title);

        // 「隠す」に戻すと、指定した内容は消える
        $this->actingAs($user)->patch($this->dashboardUrl('/links/'.$link->id.'/preview'), [
            'preview_mode' => PreviewMode::Service->value,
        ])->assertSessionHasNoErrors();

        $link->refresh();
        $this->assertSame(PreviewMode::Service, $link->preview_mode);
        $this->assertNull($link->preview_title);
        $this->assertNull($link->preview_description);
    }

    public function test_others_cannot_change_the_card(): void
    {
        $link = ShortUrl::factory()->for(User::factory())->create();

        $this->actingAs(User::factory()->create())
            ->patch($this->dashboardUrl('/links/'.$link->id.'/preview'), ['preview_mode' => PreviewMode::Service->value])
            ->assertForbidden();
    }

    public function test_expired_and_deleted_links_are_unchanged_for_crawlers(): void
    {
        $expired = ShortUrl::factory()->custom('old-link')->create(['expires_at' => now()->subDay()]);
        $deleted = ShortUrl::factory()->custom('gone-link')->create();
        $deleted->delete();

        $this->withHeader('User-Agent', self::DISCORD_BOT)->get($this->mainUrl('/old-link'))->assertGone();
        $this->withHeader('User-Agent', self::DISCORD_BOT)->get($this->mainUrl('/gone-link'))->assertNotFound();
        $this->assertSame(0, $expired->clicks()->count());
    }
}
