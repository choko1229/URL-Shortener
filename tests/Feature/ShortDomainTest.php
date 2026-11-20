<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkSource;
use App\Enums\ShortDomainMode;
use App\Models\ApiKey;
use App\Models\ShortDomain;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** 短縮URL用に追加したドメイン（ページと API／API のみ）と、API で発行した分の別枠の集計 */
final class ShortDomainTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE_HOST = 'go.localhost';

    private const API_HOST = 'api-only.localhost';

    public function test_admin_can_add_change_and_remove_domains(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get($this->dashboardUrl('/admin/domains'))
            ->assertOk()
            ->assertSee('ドメインを追加');

        $this->actingAs($admin)
            ->post($this->dashboardUrl('/admin/domains'), ['host' => ' Go.Localhost ', 'mode' => 'page'])
            ->assertSessionHas('notice');

        $domain = ShortDomain::query()->sole();
        $this->assertSame(self::PAGE_HOST, $domain->host);
        $this->assertSame(ShortDomainMode::Page, $domain->mode);

        $this->actingAs($admin)
            ->patch($this->dashboardUrl('/admin/domains/'.$domain->id), ['mode' => 'api'])
            ->assertSessionHas('notice');
        $this->assertSame(ShortDomainMode::Api, $domain->refresh()->mode);

        $this->actingAs($admin)
            ->delete($this->dashboardUrl('/admin/domains/'.$domain->id))
            ->assertSessionHas('notice');
        $this->assertSame(0, ShortDomain::query()->count());
    }

    public function test_invalid_duplicate_and_own_hosts_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        ShortDomain::query()->create(['host' => self::PAGE_HOST, 'mode' => 'page']);

        foreach (['https://x.example.com/', 'no_dot', self::PAGE_HOST, 'dash.localhost', 'localhost'] as $host) {
            $this->actingAs($admin)
                ->post($this->dashboardUrl('/admin/domains'), ['host' => $host, 'mode' => 'page'])
                ->assertSessionHasErrors('host');
        }

        $this->assertSame(1, ShortDomain::query()->count());
    }

    public function test_members_cannot_manage_domains(): void
    {
        $this->actingAs(User::factory()->create())
            ->post($this->dashboardUrl('/admin/domains'), ['host' => self::PAGE_HOST, 'mode' => 'page'])
            ->assertForbidden();
    }

    public function test_domain_with_links_cannot_be_removed(): void
    {
        $domain = ShortDomain::query()->create(['host' => self::PAGE_HOST, 'mode' => 'page']);
        ShortUrl::factory()->onDomain(self::PAGE_HOST)->create()->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->delete($this->dashboardUrl('/admin/domains/'.$domain->id))
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, '削除できません'));

        $this->assertTrue(ShortDomain::query()->whereKey($domain->id)->exists());
    }

    public function test_form_offers_only_page_domains(): void
    {
        $this->addDomains();

        $this->get($this->mainUrl('/'))
            ->assertOk()
            ->assertSee('name="domain"', false)
            ->assertSee('value="'.self::PAGE_HOST.'"', false)
            ->assertDontSee('value="'.self::API_HOST.'"', false);
    }

    public function test_form_has_no_domain_choice_without_extra_domains(): void
    {
        $this->get($this->mainUrl('/'))->assertOk()->assertDontSee('name="domain"', false);
    }

    public function test_member_can_issue_on_a_page_domain_with_the_same_code_as_the_main_domain(): void
    {
        $this->addDomains();
        $user = User::factory()->create();
        ShortUrl::factory()->custom('sale')->create(['original_url' => 'https://example.org/main']);

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links'), [
                'original_url' => 'https://example.org/go',
                'custom_slug' => 'sale',
                'domain' => self::PAGE_HOST,
                'expiry' => 'never',
            ])
            ->assertSessionHasNoErrors();

        $link = ShortUrl::query()->where('domain', self::PAGE_HOST)->sole();
        $this->assertSame('sale', $link->slug);
        $this->assertSame(LinkSource::Page, $link->source);

        // 同じドメインではもう使えない
        $this->actingAs($user)
            ->post($this->dashboardUrl('/links'), [
                'original_url' => 'https://example.org/again',
                'custom_slug' => 'sale',
                'domain' => self::PAGE_HOST,
                'expiry' => 'never',
            ])
            ->assertSessionHasErrors('custom_slug');
    }

    public function test_form_rejects_api_only_and_unknown_domains(): void
    {
        $this->addDomains();
        $user = User::factory()->create();

        foreach ([self::API_HOST, 'unknown.localhost'] as $host) {
            $this->actingAs($user)
                ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.org/', 'domain' => $host, 'expiry' => 'never'])
                ->assertSessionHasErrors('domain');
        }

        $this->assertSame(0, ShortUrl::query()->count());
    }

    public function test_links_open_only_on_their_own_domain(): void
    {
        $this->addDomains();
        ShortUrl::factory()->custom('sale')->onDomain(self::PAGE_HOST)->create();
        ShortUrl::factory()->custom('main-only')->create();

        $this->get('http://'.self::PAGE_HOST.'/sale')->assertOk()->assertViewIs('redirect.intermediate');
        $this->get($this->mainUrl('/sale'))->assertNotFound();
        $this->get('http://'.self::PAGE_HOST.'/main-only')->assertNotFound();
        $this->get($this->mainUrl('/main-only'))->assertOk();

        // 登録していないホストは開けない
        ShortUrl::factory()->custom('other')->onDomain('unknown.localhost')->create();
        $this->get('http://unknown.localhost/other')->assertNotFound();
    }

    public function test_password_protected_link_on_extra_domain_unlocks_on_the_same_domain(): void
    {
        $this->addDomains();
        ShortUrl::factory()->custom('secret')->onDomain(self::PAGE_HOST)->create(['password_hash' => Hash::make('pass1234')]);

        $this->get('http://'.self::PAGE_HOST.'/secret')
            ->assertOk()
            ->assertSee('http://'.self::PAGE_HOST.'/secret/unlock', false);

        $this->post('http://'.self::PAGE_HOST.'/secret/unlock', ['password' => 'pass1234'])
            ->assertOk()
            ->assertViewIs('redirect.intermediate');
    }

    public function test_api_can_issue_on_api_only_domain_and_marks_source(): void
    {
        $this->addDomains();
        $admin = User::factory()->admin()->create();
        [, $token] = ApiKey::issueFor($admin, 'test');

        $this->withToken($token)
            ->postJson($this->apiUrl('/v1/links'), ['url' => 'https://example.org/a', 'slug' => 'promo', 'domain' => self::API_HOST])
            ->assertCreated()
            ->assertJsonPath('data.domain', self::API_HOST)
            ->assertJsonPath('data.short_url', 'https://'.self::API_HOST.'/promo')
            ->assertJsonPath('data.source', 'api');

        $this->withToken($token)
            ->postJson($this->apiUrl('/v1/links'), ['url' => 'https://example.org/b', 'slug' => 'promo'])
            ->assertCreated()
            ->assertJsonPath('data.short_url', 'https://localhost/promo');

        $this->withToken($token)
            ->postJson($this->apiUrl('/v1/links'), ['url' => 'https://example.org/c', 'domain' => 'unknown.localhost'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        $this->withToken($token)
            ->getJson($this->apiUrl('/v1/links/promo?domain='.self::API_HOST))
            ->assertOk()
            ->assertJsonPath('data.original_url', 'https://example.org/a');

        $this->assertSame(2, ShortUrl::query()->where('source', 'api')->count());
    }

    public function test_urls_on_added_domains_cannot_be_shortened(): void
    {
        $this->addDomains();

        $this->actingAs(User::factory()->create())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://'.self::PAGE_HOST.'/x', 'expiry' => 'never'])
            ->assertSessionHasErrors('original_url');
    }

    public function test_api_links_are_counted_separately_and_do_not_use_the_monthly_limit(): void
    {
        config(['shortener.defaults.member_monthly_limit' => 2]);
        $admin = User::factory()->admin()->create();
        ShortUrl::factory()->for($admin)->count(2)->viaApi()->create();
        ShortUrl::factory()->for($admin)->create(['slug' => 'pageLnk', 'original_url' => 'https://example.org/page']);

        $this->actingAs($admin)
            ->get($this->dashboardUrl('/'))
            ->assertOk()
            ->assertSee('今月の発行数（ページ）')
            ->assertSee('今月の発行数（API）')
            ->assertSee('ページで発行')
            ->assertSee('API で発行')
            ->assertSee('localhost/pageLnk');

        // API で発行した 2 件は数えないため、上限 2 件でもまだ発行できる
        $this->actingAs($admin)
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.org/more', 'expiry' => 'never'])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertSame(2, ShortUrl::query()->where('source', 'page')->count());
    }

    public function test_history_tabs_split_page_and_api_links(): void
    {
        $admin = User::factory()->admin()->create();
        ShortUrl::factory()->for($admin)->viaApi()->create(['slug' => 'fromApi']);
        ShortUrl::factory()->for($admin)->create(['slug' => 'fromPage']);

        $this->actingAs($admin)->get($this->dashboardUrl('/'))
            ->assertSee('localhost/fromPage')
            ->assertDontSee('localhost/fromApi');

        $this->actingAs($admin)->get($this->dashboardUrl('/?source=api'))
            ->assertSee('localhost/fromApi')
            ->assertDontSee('localhost/fromPage');
    }

    public function test_members_without_api_links_see_no_tabs(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->create(['slug' => 'mine123']);

        $this->actingAs($user)->get($this->dashboardUrl('/'))
            ->assertSee('localhost/mine123')
            ->assertDontSee('今月の発行数（API）')
            ->assertDontSee('aria-label="発行元"', false);
    }

    public function test_admin_can_filter_all_links_by_source(): void
    {
        $admin = User::factory()->admin()->create();
        ShortUrl::factory()->viaApi()->create(['slug' => 'fromApi']);
        ShortUrl::factory()->create(['slug' => 'fromPage']);

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/links?source=api'))
            ->assertOk()
            ->assertSee('localhost/fromApi')
            ->assertDontSee('localhost/fromPage');
    }

    public function test_qr_code_for_extra_domain_link_encodes_its_url(): void
    {
        $this->addDomains();
        ShortUrl::factory()->custom('sale')->onDomain(self::PAGE_HOST)->create();

        $this->get($this->mainUrl('/sale/qr.svg?domain='.self::PAGE_HOST))->assertOk();
        $this->get($this->mainUrl('/sale/qr.svg'))->assertNotFound();
    }

    public function test_transfer_keeps_the_domain(): void
    {
        $this->addDomains();
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->custom('sale')->onDomain(self::PAGE_HOST)->create(['original_url' => 'https://example.org/go']);

        $csv = (string) $this->actingAs($user)->get($this->dashboardUrl('/links/export'))->getContent();
        $this->assertStringContainsString('sale,'.self::PAGE_HOST.',https://'.self::PAGE_HOST.'/sale', $csv);

        ShortUrl::query()->forceDelete();

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import'), ['file' => UploadedFile::fake()->createWithContent('links.csv', $csv)])
            ->assertSessionHas('notice');

        $this->assertSame(self::PAGE_HOST, ShortUrl::query()->where('slug', 'sale')->value('domain'));
    }

    private function addDomains(): void
    {
        ShortDomain::query()->create(['host' => self::PAGE_HOST, 'mode' => ShortDomainMode::Page]);
        ShortDomain::query()->create(['host' => self::API_HOST, 'mode' => ShortDomainMode::Api]);
    }

    private function apiUrl(string $path): string
    {
        return 'http://'.config('shortener.domains.api').$path;
    }
}
