<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\SitePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 設置した人が行うカスタマイズ（サイト名・運営者名と、Markdown の固定ページ） */
final class SiteCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_name_defaults_to_the_main_domain_and_can_be_changed(): void
    {
        // 既定はメインドメイン（配布先ごとに違う名前を直書きしないため）
        $this->get($this->mainUrl())->assertOk()->assertSee('<title>localhost - シンプルなURL短縮サービス</title>', false);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put($this->dashboardUrl('/admin/site'), [
            'name' => 'みじかいURL',
            'tagline' => '社内向けの短縮URL',
            'operator' => '情報システム部',
        ])->assertSessionHasNoErrors();

        $this->get($this->mainUrl())
            ->assertSee('<title>みじかいURL - 社内向けの短縮URL</title>', false)
            ->assertSee('みじかいURL')
            ->assertDontSee('URL-Shortener');

        $this->assertSame('みじかいURL', AppSetting::valueFor(AppSetting::SITE_NAME));
        $this->assertSame('情報システム部', AppSetting::valueFor(AppSetting::SITE_OPERATOR));
    }

    public function test_site_name_is_used_when_rejecting_own_urls(): void
    {
        AppSetting::store(AppSetting::SITE_NAME, 'みじかいURL');

        $this->from($this->mainUrl())
            ->post($this->mainUrl('/shorten'), ['original_url' => $this->mainUrl('/abc1234'), 'expiry' => '1d'])
            ->assertSessionHasErrors(['original_url' => 'みじかいURL 自身の URL は短縮できません。']);
    }

    public function test_legal_pages_are_hidden_until_the_admin_writes_them(): void
    {
        $this->get($this->mainUrl('/terms'))->assertNotFound();
        $this->get($this->mainUrl('/privacy'))->assertNotFound();
        $this->get($this->mainUrl())->assertDontSee('利用規約');

        SitePage::query()->create([
            'slug' => SitePage::TERMS,
            'title' => '利用規約',
            'body' => "## 第1条\n\n- 守ること\n\n[お問い合わせ](".$this->mainUrl('/contact').')',
        ]);

        $this->get($this->mainUrl())->assertSee('href="'.$this->mainUrl('/terms').'"', false)->assertSee('利用規約');
        $this->get($this->mainUrl('/terms'))
            ->assertOk()
            ->assertSee('<h2>第1条</h2>', false)
            ->assertSee('<li>守ること</li>', false)
            ->assertSee('お問い合わせ</a>', false);

        // プライバシーポリシーは未作成のまま
        $this->get($this->mainUrl('/privacy'))->assertNotFound();
    }

    public function test_html_in_the_markdown_body_is_removed(): void
    {
        SitePage::query()->create([
            'slug' => SitePage::PRIVACY,
            'title' => 'プライバシーポリシー',
            'body' => "<script>alert('x')</script>\n\n**太字**と[リンク](javascript:alert(1))",
        ]);

        $response = $this->get($this->mainUrl('/privacy'))->assertOk();

        $response->assertDontSee('<script>', false);
        $response->assertDontSee('javascript:alert', false);
        $response->assertSee('<strong>太字</strong>', false);
    }

    public function test_admin_can_load_a_template_review_it_and_save(): void
    {
        $admin = User::factory()->admin()->create();
        AppSetting::store(AppSetting::SITE_NAME, 'みじかいURL');
        AppSetting::store(AppSetting::SITE_OPERATOR, '情報システム部');

        // テンプレートは保存せず、編集欄に読み込むだけ
        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->post($this->dashboardUrl('/admin/site/pages/terms/template'))
            ->assertRedirect($this->dashboardUrl('/admin/site'))
            ->assertSessionHas('notice');
        $this->assertSame(0, SitePage::query()->count());

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/site'))
            ->assertOk()
            ->assertSee('第4条（禁止事項）')
            // サイト名・運営者名がひな形に差し込まれている
            ->assertSee('みじかいURL')
            ->assertSee('情報システム部');

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/site/pages/terms'), [
            'title' => '利用規約',
            'body' => "## 第1条\n\n本サービスの利用条件です。",
        ])->assertSessionHasNoErrors();

        $page = SitePage::query()->sole();
        $this->assertSame('terms', $page->slug);
        $this->get($this->mainUrl('/terms'))->assertOk()->assertSee('本サービスの利用条件です。');
    }

    public function test_admin_can_delete_a_page(): void
    {
        $admin = User::factory()->admin()->create();
        SitePage::query()->create(['slug' => SitePage::TERMS, 'title' => '利用規約', 'body' => '本文']);

        $this->actingAs($admin)->delete($this->dashboardUrl('/admin/site/pages/terms'))->assertSessionHasNoErrors();

        $this->assertSame(0, SitePage::query()->count());
        $this->get($this->mainUrl('/terms'))->assertNotFound();
    }

    public function test_unknown_pages_and_invalid_input_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post($this->dashboardUrl('/admin/site/pages/unknown/template'))->assertNotFound();
        $this->actingAs($admin)->put($this->dashboardUrl('/admin/site/pages/unknown'), ['title' => 'x', 'body' => 'y'])->assertNotFound();
        $this->actingAs($admin)->put($this->dashboardUrl('/admin/site/pages/terms'), ['title' => '利用規約', 'body' => ''])
            ->assertSessionHasErrors('body');
        $this->actingAs($admin)->put($this->dashboardUrl('/admin/site'), ['name' => '', 'tagline' => 'x', 'operator' => 'y'])
            ->assertSessionHasErrors('name');
    }

    public function test_members_cannot_change_the_site(): void
    {
        User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($member)->get($this->dashboardUrl('/admin/site'))->assertForbidden();
        $this->actingAs($member)->put($this->dashboardUrl('/admin/site'), ['name' => 'x', 'tagline' => 'y', 'operator' => 'z'])->assertForbidden();
        $this->actingAs($member)->put($this->dashboardUrl('/admin/site/pages/terms'), ['title' => 'x', 'body' => 'y'])->assertForbidden();
    }
}
