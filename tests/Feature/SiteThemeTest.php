<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\SiteIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/** サイト設定の見た目（カラー・ダークモード・書体）とサービスアイコン */
final class SiteThemeTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 の PNG（GD が無い環境でもテストできるよう、実ファイルを埋め込む） */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $iconDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iconDirectory = storage_path('framework/testing/branding-'.Str::random(8));
        $this->app->instance(SiteIcon::class, new SiteIcon($this->iconDirectory));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->iconDirectory);

        parent::tearDown();
    }

    public function test_default_look_is_light_with_the_default_color(): void
    {
        $response = $this->get($this->mainUrl())->assertOk();

        $response->assertSee('--color-primary:#2ec5e0', false);
        $response->assertSee('color-scheme:light', false);
        $response->assertSee('M+PLUS+Rounded+1c', false);
        // ライトのみの設定では切り替えボタンを出さない
        $response->assertDontSee('data-theme-toggle', false);
        $response->assertDontSee('prefers-color-scheme', false);
    }

    public function test_admin_can_change_the_colour_scheme_and_font(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->put($this->dashboardUrl('/admin/site/theme'), [
                'color' => '#8B5CF6',
                'color_scheme' => 'auto',
                'font' => 'mincho',
            ])
            ->assertRedirect($this->dashboardUrl('/admin/site'))
            ->assertSessionHas('notice');

        $this->assertSame('#8b5cf6', AppSetting::valueFor(AppSetting::SITE_THEME_COLOR));

        $response = $this->get($this->mainUrl())->assertOk();
        $response->assertSee('--color-primary:#8b5cf6', false);
        $response->assertSee('@media (prefers-color-scheme:dark)', false);
        $response->assertSee('Noto+Serif+JP', false);
        $response->assertSee('data-theme-toggle', false);
        $response->assertDontSee('M+PLUS+Rounded+1c', false);
        // 端末がダークでも「ライト表示」を選べば、入力欄などの標準の見た目もライトにする
        $response->assertSee(':root[data-theme="light"]{color-scheme:light;}', false);
    }

    public function test_invalid_theme_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->put($this->dashboardUrl('/admin/site/theme'), ['color' => 'red', 'color_scheme' => 'neon', 'font' => 'comic'])
            ->assertSessionHasErrors(['color', 'color_scheme', 'font']);

        $this->assertNull(AppSetting::valueFor(AppSetting::SITE_THEME_COLOR));
    }

    public function test_admin_can_choose_a_built_in_icon(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->post($this->dashboardUrl('/admin/site/icon'), ['icon' => 'qr-code'])
            ->assertRedirect($this->dashboardUrl('/admin/site'))
            ->assertSessionHas('notice');

        $this->assertSame('qr-code', AppSetting::valueFor(AppSetting::SITE_ICON));

        // ファビコンは画像ファイルを持たず、選んだアイコンから作る
        $this->get($this->mainUrl())->assertOk()->assertSee('data:image/svg+xml,', false);
    }

    public function test_admin_can_upload_an_icon_and_it_is_used_as_the_favicon(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->post($this->dashboardUrl('/admin/site/icon'), ['icon' => SiteIcon::UPLOADED, 'file' => $this->pngFile()])
            ->assertSessionHasNoErrors();

        $this->assertFileExists($this->iconDirectory.'/icon.png');

        $response = $this->get($this->mainUrl())->assertOk();
        $response->assertSee('rel="apple-touch-icon"', false);
        $response->assertSee($this->mainUrl('/_icon'), false);

        $this->get($this->mainUrl('/_icon'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // 内蔵アイコンに戻すと、画像は削除され配信もされない
        $this->actingAs($admin)->post($this->dashboardUrl('/admin/site/icon'), ['icon' => 'link'])->assertSessionHasNoErrors();
        $this->assertFileDoesNotExist($this->iconDirectory.'/icon.png');
        $this->get($this->mainUrl('/_icon'))->assertNotFound();
    }

    public function test_unsafe_or_oversized_icons_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        // SVG は中にスクリプトを書けるため受け付けない
        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->post($this->dashboardUrl('/admin/site/icon'), [
                'icon' => SiteIcon::UPLOADED,
                'file' => UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->post($this->dashboardUrl('/admin/site/icon'), [
                'icon' => SiteIcon::UPLOADED,
                'file' => UploadedFile::fake()->create('icon.png', SiteIcon::MAX_KILOBYTES + 1, 'image/png'),
            ])
            ->assertSessionHasErrors('file');

        // 画像を選ばずに「画像を使う」だけ送った場合
        $this->actingAs($admin)->from($this->dashboardUrl('/admin/site'))
            ->post($this->dashboardUrl('/admin/site/icon'), ['icon' => SiteIcon::UPLOADED])
            ->assertSessionHasErrors('file');

        $this->assertNull($this->app->make(SiteIcon::class)->path());
    }

    public function test_members_cannot_change_the_look(): void
    {
        User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($member)->put($this->dashboardUrl('/admin/site/theme'), [
            'color' => '#3b82f6', 'color_scheme' => 'dark', 'font' => 'gothic',
        ])->assertForbidden();

        $this->actingAs($member)->post($this->dashboardUrl('/admin/site/icon'), ['icon' => 'link'])->assertForbidden();

        $this->assertNull(AppSetting::valueFor(AppSetting::SITE_THEME_COLOR));
    }

    private function pngFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('icon.png', (string) base64_decode(self::PNG, true));
    }
}
