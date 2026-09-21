<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\LinkImportController;
use App\Models\ApiKey;
use App\Models\AppSetting;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** 1 文字のカスタムスラッグは管理者だけが使える。CSV インポートでは文字数の設定を無視する */
final class AdminSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_issue_a_one_character_slug(): void
    {
        $input = ['original_url' => 'https://example.com/', 'custom_slug' => 'a', 'expiry' => 'never'];

        $this->actingAs(User::factory()->create())->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), $input)
            ->assertSessionHasErrors(['custom_slug' => 'カスタムスラッグは3文字以上で入力してください。']);
        $this->assertSame(0, ShortUrl::query()->count());

        $this->actingAs(User::factory()->admin()->create())->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), $input)
            ->assertSessionHasNoErrors();
        $this->assertTrue(ShortUrl::query()->where('slug', 'a')->exists());

        // 1 文字のコードでもアクセスできる
        $this->get($this->mainUrl('/a'))->assertOk()->assertSee('name="ticket"', false);
    }

    /** 既存の画面と同じパスは短縮URLとして開けないため、予約語を無視できる管理者でも使えない */
    public function test_slugs_that_would_be_hidden_by_site_pages_are_rejected_even_for_admins(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['up', 'terms', 'privacy', 'contact', 'delete'] as $slug) {
            $this->actingAs($admin)->from($this->dashboardUrl())
                ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => $slug, 'expiry' => 'never'])
                ->assertSessionHasErrors(['custom_slug' => 'このカスタムスラッグはサイト内のページと同じ URL になるため使えません。']);
        }

        // GET の画面が無いパス（POST 専用の /shorten）は短縮URLとして開けるため使える
        $this->actingAs($admin)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => 'shorten', 'expiry' => 'never'])
            ->assertSessionHasNoErrors();
        $this->get($this->mainUrl('/shorten'))->assertOk()->assertSee('name="ticket"', false);

        $this->assertSame(1, ShortUrl::query()->count());
    }

    public function test_forms_tell_admins_that_one_character_is_allowed(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get($this->dashboardUrl())
            ->assertOk()
            ->assertSee('minlength="1"', false)
            ->assertSee('1〜20文字');

        $this->actingAs(User::factory()->create())->get($this->dashboardUrl())
            ->assertOk()
            ->assertSee('minlength="3"', false)
            ->assertSee('3〜20文字');
    }

    public function test_only_admins_can_change_a_slug_to_one_character(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $adminLink = ShortUrl::factory()->for($admin)->custom('admin-link')->create();
        $memberLink = ShortUrl::factory()->for($member)->custom('member-link')->create();

        $this->actingAs($member)->from($this->dashboardUrl('/links/'.$memberLink->id))
            ->patch($this->dashboardUrl('/links/'.$memberLink->id.'/slug'), ['custom_slug' => 'm'])
            ->assertSessionHasErrors('custom_slug');

        $this->actingAs($admin)->from($this->dashboardUrl('/links/'.$adminLink->id))
            ->patch($this->dashboardUrl('/links/'.$adminLink->id.'/slug'), ['custom_slug' => 'z'])
            ->assertSessionHasNoErrors();

        $this->assertSame('z', $adminLink->refresh()->slug);
        $this->assertSame('member-link', $memberLink->refresh()->slug);
    }

    public function test_api_accepts_a_one_character_slug(): void
    {
        [, $token] = ApiKey::issueFor(User::factory()->admin()->create(), 'テスト');

        $this->withToken($token)
            ->postJson('http://'.config('shortener.domains.api').'/v1/links', ['url' => 'https://example.com/', 'slug' => 'x'])
            ->assertCreated();

        $this->withToken($token)
            ->getJson('http://'.config('shortener.domains.api').'/v1/links/x')
            ->assertOk()
            ->assertJsonPath('data.code', 'x');
    }

    public function test_csv_import_ignores_the_slug_length_settings(): void
    {
        $admin = User::factory()->admin()->create();
        // 設定で文字数を狭めていても、CSV では 1〜20 文字（保存できる上限）まで受け付ける
        AppSetting::store('custom_slug_min_length', 5);
        AppSetting::store('custom_slug_max_length', 8);

        $csv = "url,slug\n"
            ."https://example.com/1,b\n"
            .'https://example.com/2,'.str_repeat('c', 20)."\n"
            .'https://example.com/3,'.str_repeat('d', 21)."\n";

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), ['file' => UploadedFile::fake()->createWithContent('links.csv', $csv)])
            ->assertSessionHas('notice');

        $this->assertTrue(ShortUrl::query()->where('slug', 'b')->exists());
        $this->assertTrue(ShortUrl::query()->where('slug', str_repeat('c', 20))->exists());

        // 21 文字は列に入らないため取り込まない
        $result = session(LinkImportController::RESULT_SESSION_KEY);
        $this->assertSame([4], array_keys($result->errors));
        $this->assertStringContainsString('20文字以内', $result->errors[4]);
    }
}
