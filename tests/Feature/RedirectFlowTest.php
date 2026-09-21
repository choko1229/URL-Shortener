<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\SlugType;
use App\Models\AppSetting;
use App\Models\ShortUrl;
use App\Services\Redirect\RedirectTicketCodec;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\MmdbFixture;
use Tests\TestCase;

final class RedirectFlowTest extends TestCase
{
    use RefreshDatabase;

    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    public function test_intermediate_page_posts_ticket_to_redirect_domain(): void
    {
        ShortUrl::factory()->create(['slug' => 'AbC1234', 'slug_type' => SlugType::Random]);

        $response = $this->get($this->mainUrl('/AbC1234'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('action="'.$this->redirectUrl('/go').'"', false)
            ->assertSee('data-auto-submit', false)
            // 画面全体のスクリプトを待たず、その場で送信する（二重送信しないよう印を付ける）
            ->assertSee("form.dataset.autoSubmitted = 'true';", false)
            ->assertSee('form.submit();', false);

        $this->assertNotSame('', $this->ticketFrom($response));
    }

    public function test_random_codes_match_case_insensitively_but_custom_slugs_do_not(): void
    {
        ShortUrl::factory()->create(['slug' => 'AbC1234', 'slug_type' => SlugType::Random]);
        ShortUrl::factory()->custom('MyLink')->create();

        $this->get($this->mainUrl('/abc1234'))->assertOk();
        $this->get($this->mainUrl('/MyLink'))->assertOk();
        $this->get($this->mainUrl('/mylink'))->assertNotFound();
    }

    public function test_deleted_links_are_not_found_and_expired_links_show_dedicated_page(): void
    {
        ShortUrl::factory()->custom('deleted-link')->create()->delete();
        ShortUrl::factory()->custom('expired-link')->expired()->create();

        $this->get($this->mainUrl('/deleted-link'))->assertNotFound();
        $this->get($this->mainUrl('/expired-link'))
            ->assertStatus(410)
            ->assertSee('このリンクは有効期限が切れています');
    }

    public function test_password_protected_link_locks_out_after_five_failures(): void
    {
        $link = ShortUrl::factory()->custom('secret-link')->create(['password_hash' => bcrypt('correct-pass')]);

        $this->get($this->mainUrl('/secret-link'))->assertOk()->assertSee('パスワードが必要です');

        for ($i = 4; $i >= 1; $i--) {
            $this->post($this->mainUrl('/secret-link/unlock'), ['password' => 'wrong'])
                ->assertStatus(422)
                ->assertSee("あと {$i} 回");
        }

        $this->post($this->mainUrl('/secret-link/unlock'), ['password' => 'wrong'])
            ->assertStatus(429)
            ->assertSee('入力を一時的に停止しています');

        // ロック中は正しいパスワードでも開けない
        $this->post($this->mainUrl('/secret-link/unlock'), ['password' => 'correct-pass'])->assertStatus(429);

        // 15 分後に解除される
        $this->travel(16)->minutes();
        $response = $this->post($this->mainUrl('/secret-link/unlock'), ['password' => 'correct-pass'])->assertOk();

        $this->post($this->redirectUrl('/go'), ['ticket' => $this->ticketFrom($response)])
            ->assertOk()
            ->assertSee($link->original_url);
    }

    public function test_ticket_without_password_verification_cannot_open_protected_link(): void
    {
        $link = ShortUrl::factory()->custom('secret-link')->create(['password_hash' => bcrypt('correct-pass')]);
        $ticket = app(RedirectTicketCodec::class)->encode($link, null, passwordVerified: false, now: CarbonImmutable::now());

        $this->post($this->redirectUrl('/go'), ['ticket' => $ticket])->assertNotFound();
    }

    public function test_redirect_page_records_click_once_per_ticket(): void
    {
        $link = ShortUrl::factory()->custom('track-me')->create(['original_url' => 'https://example.com/landing', 'click_count' => 0]);

        $ticket = $this->ticketFrom(
            $this->withHeader('Referer', 'https://Twitter.com/some/path?secret=1')->get($this->mainUrl('/track-me'))
        );

        $this->withHeader('User-Agent', self::DESKTOP_UA)
            ->post($this->redirectUrl('/go'), ['ticket' => $ticket])
            ->assertOk()
            ->assertSee('https://example.com/landing')
            ->assertSee('data-check-url="'.$this->redirectUrl('/check').'"', false);

        // 再読み込み（同じチケット）では数えない
        $this->post($this->redirectUrl('/go'), ['ticket' => $ticket])->assertOk();

        $link->refresh();
        $this->assertSame(1, $link->click_count);
        $this->assertNotNull($link->last_clicked_at);

        $click = $link->clicks()->sole();
        $this->assertSame('twitter.com', $click->referrer_host);
        $this->assertSame(DeviceType::Desktop, $click->device_type);
        $this->assertNull($click->country_code);
    }

    public function test_click_records_country_from_the_automatically_downloaded_database(): void
    {
        $directory = storage_path('framework/testing/geoip-'.Str::random(8));
        File::ensureDirectoryExists($directory);
        File::put($directory.'/dbip-country-lite.mmdb', MmdbFixture::build('8.8.8.0/24', 'US'));
        config([
            'shortener.geoip.manual_database' => $directory.'/GeoLite2-Country.mmdb',
            'shortener.geoip.auto_database' => $directory.'/dbip-country-lite.mmdb',
        ]);

        try {
            $link = ShortUrl::factory()->custom('from-us')->create();
            $ticket = $this->ticketFrom($this->get($this->mainUrl('/from-us')));

            $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
                ->post($this->redirectUrl('/go'), ['ticket' => $ticket])
                ->assertOk();

            $this->assertSame('US', $link->clicks()->sole()->country_code);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_invalid_or_expired_ticket_is_rejected(): void
    {
        $link = ShortUrl::factory()->custom('track-me')->create();

        $this->post($this->redirectUrl('/go'), ['ticket' => 'tampered'])->assertStatus(400);

        $ticket = app(RedirectTicketCodec::class)->encode($link, null, false, CarbonImmutable::now()->subMinutes(11));
        $this->post($this->redirectUrl('/go'), ['ticket' => $ticket])->assertStatus(400);
        $this->postJson($this->redirectUrl('/check'), ['ticket' => $ticket])->assertStatus(400)->assertJson(['status' => 'invalid']);

        $this->assertSame(0, $link->clicks()->count());
    }

    public function test_check_reports_unknown_when_safe_browsing_is_not_configured(): void
    {
        $ticket = $this->ticketFor(ShortUrl::factory()->create(['original_url' => 'https://example.com/']));

        $this->postJson($this->redirectUrl('/check'), ['ticket' => $ticket])
            ->assertOk()
            ->assertJson(['status' => 'unknown', 'destination' => 'https://example.com/'])
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, '設定されていません'));
    }

    public function test_check_allows_safe_urls_and_caches_the_result(): void
    {
        AppSetting::store(AppSetting::SAFE_BROWSING_API_KEY, 'safe-browsing-key-abcdefghij', encrypt: true);
        Http::fake(['safebrowsing.googleapis.com/*' => Http::response([], 200)]);
        $ticket = $this->ticketFor(ShortUrl::factory()->create(['original_url' => 'https://example.com/ok']));

        $this->postJson($this->redirectUrl('/check'), ['ticket' => $ticket])
            ->assertOk()
            ->assertJson(['status' => 'safe', 'destination' => 'https://example.com/ok']);
        $this->postJson($this->redirectUrl('/check'), ['ticket' => $ticket])->assertJson(['status' => 'safe']);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->hasHeader('X-Goog-Api-Key', 'safe-browsing-key-abcdefghij')
            && ! str_contains($request->url(), 'safe-browsing-key')
            && $request['threatInfo']['threatEntries'][0]['url'] === 'https://example.com/ok');
    }

    public function test_check_blocks_unsafe_urls_without_revealing_destination(): void
    {
        AppSetting::store(AppSetting::SAFE_BROWSING_API_KEY, 'safe-browsing-key-abcdefghij', encrypt: true);
        Http::fake(['safebrowsing.googleapis.com/*' => Http::response([
            'matches' => [['threatType' => 'SOCIAL_ENGINEERING', 'threat' => ['url' => 'https://evil.example/']]],
        ], 200)]);
        $ticket = $this->ticketFor(ShortUrl::factory()->create(['original_url' => 'https://evil.example/']));

        $this->postJson($this->redirectUrl('/check'), ['ticket' => $ticket])
            ->assertOk()
            ->assertJson(['status' => 'unsafe', 'destination' => null, 'threats' => ['フィッシング・詐欺']]);
    }

    public function test_check_reports_unknown_when_safe_browsing_fails(): void
    {
        AppSetting::store(AppSetting::SAFE_BROWSING_API_KEY, 'safe-browsing-key-abcdefghij', encrypt: true);
        Http::fake(['safebrowsing.googleapis.com/*' => Http::response(['error' => 'quota'], 429)]);
        $ticket = $this->ticketFor(ShortUrl::factory()->create(['original_url' => 'https://example.com/']));

        $this->postJson($this->redirectUrl('/check'), ['ticket' => $ticket])
            ->assertOk()
            ->assertJson(['status' => 'unknown', 'destination' => 'https://example.com/']);
    }

    public function test_redirect_domain_root_goes_to_top_page(): void
    {
        $this->get($this->redirectUrl())->assertRedirect(route('main.home'));
    }

    private function ticketFor(ShortUrl $link): string
    {
        return app(RedirectTicketCodec::class)->encode($link, null, false, CarbonImmutable::now());
    }

    private function ticketFrom(TestResponse $response): string
    {
        $matched = preg_match('/name="ticket" value="([^"]+)"/', (string) $response->getContent(), $matches);
        $this->assertSame(1, $matched, 'チケットが見つかりません。');

        return html_entity_decode($matches[1]);
    }
}
