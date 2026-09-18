<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReservedWordCategory;
use App\Enums\SlugType;
use App\Models\AppSetting;
use App\Models\ReservedWord;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\ShortUrl\ShortUrlIssuer;
use App\ViewModels\IssuedLinkData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class IssueShortUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_issue_random_code_with_deletion_token_and_qr_code(): void
    {
        $this->from($this->mainUrl())
            ->post($this->mainUrl('/shorten'), [
                'original_url' => 'https://example.com/path?q=1',
                'expiry' => '7d',
                'password' => 'secret-pass',
            ])
            ->assertRedirect($this->mainUrl())
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('_old_input.password');

        $link = ShortUrl::query()->sole();
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{7}\z/', $link->slug);
        $this->assertSame(SlugType::Random, $link->slug_type);
        $this->assertNull($link->user_id);
        $this->assertSame(ShortUrlIssuer::hashClientIp('127.0.0.1'), $link->creator_ip_hash);
        $this->assertTrue(Hash::check('secret-pass', (string) $link->password_hash));
        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), $link->expires_at?->getTimestamp(), 5);

        $issued = session(IssuedLinkData::SESSION_KEY);
        $this->assertInstanceOf(IssuedLinkData::class, $issued);
        $this->assertSame('https://localhost/'.$link->slug, $issued->shortUrl);
        $this->assertSame(hash('sha256', (string) $issued->deletionToken), $link->deletion_token_hash);
        $this->assertStringStartsWith('data:image/svg+xml', (string) $issued->qrCodeDataUri);

        $this->get($this->mainUrl())
            ->assertOk()
            ->assertSee('発行された短縮URL')
            ->assertSee('localhost/'.$link->slug)
            ->assertSee((string) $issued->deletionToken)
            ->assertSee('パスワード保護あり');
    }

    public function test_guest_is_rate_limited_after_successful_issue(): void
    {
        $input = ['original_url' => 'https://example.com/', 'expiry' => '1d'];

        $this->from($this->mainUrl())->post($this->mainUrl('/shorten'), $input)->assertSessionHasNoErrors();
        $this->from($this->mainUrl())->post($this->mainUrl('/shorten'), $input)->assertSessionHas('error');

        $this->assertSame(1, ShortUrl::query()->count());
    }

    public function test_guest_monthly_limit_is_enforced_by_ip(): void
    {
        ShortUrl::factory()->count(5)->create([
            'user_id' => null,
            'creator_ip_hash' => ShortUrlIssuer::hashClientIp('127.0.0.1'),
        ]);

        $this->from($this->mainUrl())
            ->post($this->mainUrl('/shorten'), ['original_url' => 'https://example.com/', 'expiry' => '1d'])
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '発行上限（5件）'));

        $this->assertSame(5, ShortUrl::query()->count());
    }

    public function test_member_can_issue_custom_slug_without_expiry(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), [
                'original_url' => 'https://example.com/member',
                'custom_slug' => 'My-Link_1',
                'expiry' => 'never',
            ])
            ->assertRedirect($this->dashboardUrl())
            ->assertSessionHasNoErrors();

        $link = ShortUrl::query()->sole();
        $this->assertSame('My-Link_1', $link->slug);
        $this->assertSame(SlugType::Custom, $link->slug_type);
        $this->assertSame($user->id, $link->user_id);
        $this->assertNull($link->expires_at);
        $this->assertNull($link->deletion_token_hash);
        $this->assertNull($link->creator_ip_hash);

        $this->actingAs($user)->get($this->dashboardUrl())
            ->assertOk()
            ->assertSee('発行された短縮URL')
            ->assertDontSee('削除用トークン');
    }

    public function test_reserved_words_are_rejected_except_for_admin(): void
    {
        ReservedWord::query()->create(['word' => 'admin', 'category' => ReservedWordCategory::System]);
        $input = ['original_url' => 'https://example.com/', 'custom_slug' => 'Admin', 'expiry' => 'never'];

        $this->actingAs(User::factory()->create())
            ->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), $input)
            ->assertSessionHasErrors(['custom_slug' => 'このカスタムスラッグは予約されているため使えません。']);

        $this->actingAs(User::factory()->admin()->create())
            ->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), $input)
            ->assertSessionHasNoErrors();

        $this->assertTrue(ShortUrl::query()->where('slug', 'Admin')->exists());
    }

    public function test_custom_slug_uniqueness_follows_case_rules(): void
    {
        ShortUrl::factory()->create(['slug' => 'AbCdEfG', 'slug_type' => SlugType::Random]);
        ShortUrl::factory()->custom('MyLink')->create();
        $user = User::factory()->create();

        // ランダムコードとは大文字小文字違いでも衝突扱い
        $this->actingAs($user)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => 'abcdefg', 'expiry' => 'never'])
            ->assertSessionHasErrors(['custom_slug' => 'このカスタムスラッグはすでに使われています。']);

        // カスタムスラッグ同士は大文字小文字を区別する
        $this->actingAs($user)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => 'mylink', 'expiry' => 'never'])
            ->assertSessionHasNoErrors();

        // 削除済み（欠番）のコードは再利用できない
        ShortUrl::factory()->custom('gone-link')->create()->delete();
        $this->actingAs($user)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => 'gone-link', 'expiry' => 'never'])
            ->assertSessionHasErrors('custom_slug');
    }

    public function test_member_monthly_limit_counts_deleted_links(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->count(60)->for($user)->create()->each->delete();

        $this->actingAs($user)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'expiry' => 'never'])
            ->assertSessionHas('error', '今月の発行上限（60件）に達しました。');
    }

    public function test_own_domain_cannot_be_shortened(): void
    {
        $this->from($this->mainUrl())
            ->post($this->mainUrl('/shorten'), ['original_url' => 'https://localhost/abc1234', 'expiry' => '1d'])
            ->assertSessionHasErrors(['original_url' => 'chok.ooo 自身の URL は短縮できません。']);
    }

    public function test_recaptcha_is_required_for_guests_when_configured(): void
    {
        AppSetting::store(AppSetting::RECAPTCHA_SITE_KEY, 'site-key-abcdefghijklmnop');
        AppSetting::store(AppSetting::RECAPTCHA_SECRET_KEY, 'secret-key-abcdefghijklmnop', encrypt: true);

        $this->get($this->mainUrl())
            ->assertSee('data-recaptcha-site-key="site-key-abcdefghijklmnop"', false)
            ->assertDontSee('secret-key-abcdefghijklmnop');

        Http::fake(['www.google.com/recaptcha/*' => Http::sequence()
            ->push(['success' => true, 'score' => 0.1, 'action' => 'shorten'])
            ->push(['success' => true, 'score' => 0.9, 'action' => 'shorten']),
        ]);
        $input = ['original_url' => 'https://example.com/', 'expiry' => '1d', 'recaptcha_token' => 'token'];

        $this->from($this->mainUrl())->post($this->mainUrl('/shorten'), $input)
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'スパム対策'));
        $this->assertSame(0, ShortUrl::query()->count());

        $this->from($this->mainUrl())->post($this->mainUrl('/shorten'), $input)->assertSessionHasNoErrors();
        $this->assertSame(1, ShortUrl::query()->count());

        Http::assertSent(fn ($request): bool => $request['secret'] === 'secret-key-abcdefghijklmnop' && $request['response'] === 'token');
    }
}
