<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\CardPreview\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 発行フォームの「X に貼ったときの見え方」: 転送先のカード情報の取得 */
final class CardPreviewTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, list<string>> */
    private array $dns = [
        'example.com' => ['93.184.215.14'],
        'cdn.example.com' => ['93.184.215.15'],
        'internal.example.com' => ['10.0.0.5'],
        'rebind.example.com' => ['93.184.215.16', '127.0.0.1'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $dns = $this->dns;
        $this->app->instance(HostResolver::class, new class($dns) extends HostResolver
        {
            /** @param  array<string, list<string>>  $dns */
            public function __construct(private readonly array $dns) {}

            public function resolve(string $host): array
            {
                return $this->dns[$host] ?? [];
            }
        });
    }

    public function test_returns_the_destination_card(): void
    {
        Http::fake(['example.com/*' => Http::response(
            '<html><head><meta charset="utf-8"><title>ページ</title>'
            .'<meta property="og:title" content="夏のイベント">'
            .'<meta property="og:description" content="  8月に開催します  ">'
            .'<meta property="og:image" content="/images/ogp.png">'
            .'<meta name="twitter:card" content="summary_large_image">'
            .'</head><body>本文</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        )]);

        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'https://example.com/event'])
            ->assertOk()
            ->assertExactJson(['card' => [
                'title' => '夏のイベント',
                'description' => '8月に開催します',
                'image' => 'https://example.com/images/ogp.png',
                'large' => true,
            ]]);
    }

    public function test_falls_back_to_title_tag_and_small_card(): void
    {
        Http::fake(['example.com/*' => Http::response('<html><head><title> ただのページ </title></head></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'https://example.com/plain'])
            ->assertOk()
            ->assertJsonPath('card.title', 'ただのページ')
            ->assertJsonPath('card.image', null)
            ->assertJsonPath('card.large', false);
    }

    public function test_follows_redirects_to_public_hosts(): void
    {
        Http::fake([
            // 'example.com/*' だと cdn.example.com にも一致するため、スキームから指定する
            'https://example.com/*' => Http::response('', 301, ['Location' => 'https://cdn.example.com/final']),
            'https://cdn.example.com/*' => Http::response('<head><meta property="og:title" content="移動先"></head>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'https://example.com/old'])
            ->assertOk()
            ->assertJsonPath('card.title', '移動先');
    }

    public function test_does_not_access_private_addresses(): void
    {
        Http::fake();

        foreach ([
            'http://127.0.0.1/admin',
            'http://[::1]/',
            'http://192.168.1.1/',
            'http://169.254.169.254/latest/meta-data/',
            'http://100.64.0.1/',
            'http://internal.example.com/',
            'http://rebind.example.com/',
            'http://unknown-host.example.com/',
            'https://example.com:8080/',
        ] as $url) {
            $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => $url])
                ->assertOk()
                ->assertJsonPath('card', null);
        }

        Http::assertNothingSent();
    }

    public function test_does_not_follow_redirects_into_the_private_network(): void
    {
        Http::fake(['example.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/'])]);

        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'https://example.com/trap'])
            ->assertOk()
            ->assertJsonPath('card', null);

        Http::assertSentCount(1);
        Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), '169.254.169.254'));
    }

    public function test_ignores_non_html_responses(): void
    {
        Http::fake(['example.com/*' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf'])]);

        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'https://example.com/file.pdf'])
            ->assertOk()
            ->assertJsonPath('card', null);
    }

    public function test_rejects_invalid_urls(): void
    {
        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'javascript:alert(1)'])
            ->assertUnprocessable();

        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'http://'.config('shortener.domains.main').'/abc'])
            ->assertUnprocessable();
    }

    public function test_results_are_cached(): void
    {
        Http::fake(['example.com/*' => Http::response('<head><meta property="og:title" content="一度だけ"></head>', 200, ['Content-Type' => 'text/html'])]);

        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'https://example.com/cached'])->assertJsonPath('card.title', '一度だけ');
        $this->postJson($this->mainUrl('/shorten/card-preview'), ['url' => 'https://example.com/cached'])->assertJsonPath('card.title', '一度だけ');

        Http::assertSentCount(1);
    }

    public function test_dashboard_has_its_own_endpoint(): void
    {
        Http::fake(['example.com/*' => Http::response('<head><meta property="og:title" content="ダッシュボードから"></head>', 200, ['Content-Type' => 'text/html'])]);

        $this->postJson($this->dashboardUrl('/links/card-preview'), ['url' => 'https://example.com/'])
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson($this->dashboardUrl('/links/card-preview'), ['url' => 'https://example.com/'])
            ->assertOk()
            ->assertJsonPath('card.title', 'ダッシュボードから');
    }

    public function test_form_shows_the_new_controls(): void
    {
        $this->get($this->mainUrl())
            ->assertOk()
            ->assertSee('data-password-reveal', false)
            ->assertSee('自動生成')
            ->assertSee('data-password-strength', false)
            ->assertSee('data-expiry-until', false)
            ->assertSee('X に貼ったときの見え方')
            ->assertSee('data-fetch-url="'.$this->mainUrl('/shorten/card-preview').'"', false);
    }
}
