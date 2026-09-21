<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ShortUrl;
use App\Models\SitePage;
use App\Models\User;
use App\Support\SitePaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** 固定ページの URL を変えて、元の語（contact など）を管理者が短縮URLとして使えるようにする */
final class SitePathsTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT_PATHS = ['terms' => 'terms', 'privacy' => 'privacy', 'contact' => 'contact', 'delete' => 'delete'];

    public function test_admin_can_move_a_page_and_use_the_old_word_as_a_short_link(): void
    {
        $admin = User::factory()->admin()->create();

        // 変える前は、管理者でも「contact」は作れない
        $this->actingAs($admin)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => 'contact', 'expiry' => 'never'])
            ->assertSessionHasErrors('custom_slug');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->put($this->dashboardUrl('/admin/site/paths'), ['paths' => ['contact' => 'inquiry'] + self::DEFAULT_PATHS])
            ->assertRedirect($this->dashboardUrl('/admin/site'))
            ->assertSessionHas('notice');
        $this->reloadRoutes();

        // お問い合わせは新しい URL で開け、リンクも新しい URL になる
        $this->get($this->mainUrl('/inquiry'))->assertOk()->assertSee('お問い合わせ');
        $this->get($this->mainUrl('/terms'))->assertNotFound();
        $this->assertSame($this->mainUrl('/inquiry'), route('main.contact'));
        $this->get($this->mainUrl())->assertSee('href="'.$this->mainUrl('/inquiry').'"', false);

        // 元の「contact」は管理者の短縮URLとして使える
        $this->actingAs($admin)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/contact-us', 'custom_slug' => 'contact', 'expiry' => 'never'])
            ->assertSessionHasNoErrors();
        $this->get($this->mainUrl('/contact'))->assertOk()->assertSee('name="ticket"', false);

        // 新しい URL は短縮URLとしては作れない（ページが優先されて開けなくなるため）
        $this->actingAs($admin)->from($this->dashboardUrl())
            ->post($this->dashboardUrl('/links'), ['original_url' => 'https://example.com/', 'custom_slug' => 'inquiry', 'expiry' => 'never'])
            ->assertSessionHasErrors('custom_slug');
    }

    public function test_all_pages_can_be_moved_and_swapped(): void
    {
        $admin = User::factory()->admin()->create();
        SitePage::query()->create(['slug' => SitePage::TERMS, 'title' => '利用規約', 'body' => '規約の本文']);
        SitePage::query()->create(['slug' => SitePage::PRIVACY, 'title' => 'プライバシーポリシー', 'body' => 'ポリシーの本文']);

        // terms と privacy の入れ替えもできる
        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->put($this->dashboardUrl('/admin/site/paths'), ['paths' => ['terms' => 'privacy', 'privacy' => 'terms', 'contact' => 'ask', 'delete' => 'remove']])
            ->assertSessionHasNoErrors();
        $this->reloadRoutes();

        $this->get($this->mainUrl('/privacy'))->assertOk()->assertSee('規約の本文');
        $this->get($this->mainUrl('/terms'))->assertOk()->assertSee('ポリシーの本文');
        $this->get($this->mainUrl('/ask'))->assertOk();
        $this->get($this->mainUrl('/remove'))->assertOk();
        $this->assertSame(['terms' => 'privacy', 'privacy' => 'terms', 'contact' => 'ask', 'delete' => 'remove'], $this->app->make(SitePaths::class)->all());
    }

    public function test_paths_that_would_break_something_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        ShortUrl::factory()->custom('in-use')->create();

        $cases = [
            // ページ同士の重複
            ['contact' => 'terms', 'expect' => '利用規約と同じです'],
            // 使われている短縮URL
            ['contact' => 'in-use', 'expect' => 'すでに短縮URLとして使われています'],
            // サイトのほかの機能（死活確認・アイコン・セットアップ）
            ['contact' => 'up', 'expect' => 'ほかの機能で使っている'],
            ['contact' => '_icon', 'expect' => 'ほかの機能で使っている'],
            ['contact' => 'install', 'expect' => 'ほかの機能で使っている'],
            // 形式
            ['contact' => 'a/b', 'expect' => '/ は使えません'],
            ['contact' => '', 'expect' => 'URL を入力してください'],
        ];

        foreach ($cases as $case) {
            $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
                ->put($this->dashboardUrl('/admin/site/paths'), ['paths' => ['contact' => $case['contact']] + self::DEFAULT_PATHS])
                ->assertSessionHasErrors('paths.contact');

            $this->assertStringContainsString($case['expect'], (string) session('errors')->first('paths.contact'), "「{$case['contact']}」");
        }

        $this->assertSame(self::DEFAULT_PATHS, $this->app->make(SitePaths::class)->all());
    }

    public function test_members_cannot_change_the_paths(): void
    {
        User::factory()->admin()->create();

        $this->actingAs(User::factory()->create())
            ->put($this->dashboardUrl('/admin/site/paths'), ['paths' => ['contact' => 'inquiry'] + self::DEFAULT_PATHS])
            ->assertForbidden();

        $this->assertSame('contact', $this->app->make(SitePaths::class)->path('contact'));
    }

    /** 保存した URL は次のリクエストのルート登録から効くため、テストでは登録し直す */
    private function reloadRoutes(): void
    {
        $this->app->make(SitePaths::class)->forget();

        $router = $this->app->make('router');
        $router->setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));

        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
    }
}
