<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PreviewMode;
use App\Enums\ReservedWordCategory;
use App\Http\Controllers\Admin\LinkImportController;
use App\Models\ReservedWord;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\ShortUrl\CsvImporter;
use App\Services\ShortUrl\CsvImportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** CSV でまとめて短縮URLを発行する（管理者のみ） */
final class LinkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_the_template(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get($this->dashboardUrl('/admin/links/import/template'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('url-shortener-import-template.csv', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->getContent();
        // Excel で開いても文字化けしないよう BOM を付ける
        $this->assertStringStartsWith("\u{FEFF}url,slug,expires_at", (string) $csv);
        $this->assertStringContainsString('https://example.com/very/long/path', (string) $csv);
    }

    public function test_admin_can_import_links_from_a_csv(): void
    {
        $admin = User::factory()->admin()->create();

        $csv = <<<'CSV'
        url,slug,expires_at,password,preview_mode,preview_title
        https://example.com/a,spring-sale,,,,
        https://example.com/b,,2026-12-31 23:59,,service,
        https://example.com/c,,,secret123,custom,秋のイベント
        CSV;

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), ['file' => $this->csvFile($csv)])
            ->assertRedirect($this->dashboardUrl('/admin/links'))
            ->assertSessionHas('notice', fn (string $message): bool => str_contains($message, '3件'));

        $this->assertSame(3, ShortUrl::query()->count());

        $first = ShortUrl::query()->where('slug', 'spring-sale')->sole();
        $this->assertSame('https://example.com/a', $first->original_url);
        $this->assertSame($admin->id, $first->user_id);
        $this->assertNull($first->expires_at);

        $second = ShortUrl::query()->where('original_url', 'https://example.com/b')->sole();
        $this->assertSame(PreviewMode::Service, $second->preview_mode);
        $this->assertSame('2026-12-31 14:59', $second->expires_at?->format('Y-m-d H:i'));

        $third = ShortUrl::query()->where('original_url', 'https://example.com/c')->sole();
        $this->assertNotNull($third->password_hash);
        $this->assertSame('秋のイベント', $third->preview_title);
    }

    public function test_rows_with_problems_are_skipped_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        ReservedWord::query()->create(['word' => 'admin', 'category' => ReservedWordCategory::System]);
        ShortUrl::factory()->custom('taken')->create();

        $csv = <<<'CSV'
        url,slug,expires_at
        https://example.com/ok,,
        not-a-url,,
        https://example.com/dup,taken,
        https://example.com/past,,2000-01-01 00:00
        ,,
        https://example.com/bad-slug,"a b",
        CSV;

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), ['file' => $this->csvFile($csv)])
            ->assertSessionHas('notice');

        // 正しい1行だけが登録される（空行は数えない）
        $this->assertSame(1, ShortUrl::query()->where('original_url', 'https://example.com/ok')->count());

        $result = session(LinkImportController::RESULT_SESSION_KEY);
        $this->assertInstanceOf(CsvImportResult::class, $result);
        $this->assertSame(1, $result->imported);
        $this->assertSame([3, 4, 5, 7], array_keys($result->errors));
        $this->assertStringContainsString('url は http', $result->errors[3]);
        $this->assertStringContainsString('すでに使われています', $result->errors[4]);
        $this->assertStringContainsString('現在より後', $result->errors[5]);
        $this->assertStringContainsString('slug には', $result->errors[7]);
    }

    public function test_admin_can_use_reserved_words_but_not_the_own_domain(): void
    {
        $admin = User::factory()->admin()->create();
        ReservedWord::query()->create(['word' => 'admin', 'category' => ReservedWordCategory::System]);

        $csv = "url,slug\nhttps://example.com/x,admin\n".$this->mainUrl('/abc1234').",\n";

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), ['file' => $this->csvFile($csv)]);

        // 管理者は予約語も使える（requirements.md 4-2）が、自ドメインは短縮できない
        $this->assertTrue(ShortUrl::query()->where('slug', 'admin')->exists());
        $result = session(LinkImportController::RESULT_SESSION_KEY);
        $this->assertStringContainsString('自身の URL は短縮できません', $result->errors[3]);
    }

    public function test_shift_jis_files_are_read_correctly(): void
    {
        $admin = User::factory()->admin()->create();

        $csv = (string) mb_convert_encoding(
            "url,preview_mode,preview_title\nhttps://example.com/sjis,custom,秋のイベント\n",
            'SJIS-win',
            'UTF-8',
        );

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), ['file' => $this->csvFile($csv)])
            ->assertSessionHas('notice');

        $this->assertSame('秋のイベント', ShortUrl::query()->sole()->preview_title);
    }

    public function test_a_csv_without_the_url_column_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), ['file' => $this->csvFile("link,slug\nhttps://example.com/a,\n")])
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '取り込めませんでした'));

        $this->assertSame(0, ShortUrl::query()->count());
        $this->assertStringContainsString('url がありません', session(LinkImportController::RESULT_SESSION_KEY)->errors[1]);
    }

    public function test_too_many_rows_are_rejected_before_importing(): void
    {
        $admin = User::factory()->admin()->create();

        $csv = "url\n".str_repeat("https://example.com/a\n", CsvImporter::MAX_ROWS + 1);

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), ['file' => $this->csvFile($csv)])
            ->assertSessionHas('error');

        $this->assertSame(0, ShortUrl::query()->count());
    }

    public function test_only_csv_files_are_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), [
                'file' => UploadedFile::fake()->createWithContent('links.php', '<?php echo 1;'),
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/links'))
            ->post($this->dashboardUrl('/admin/links/import'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_members_cannot_import(): void
    {
        User::factory()->admin()->create();

        $this->actingAs(User::factory()->create())
            ->post($this->dashboardUrl('/admin/links/import'), ['file' => $this->csvFile("url\nhttps://example.com/a\n")])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get($this->dashboardUrl('/admin/links/import/template'))
            ->assertForbidden();

        $this->assertSame(0, ShortUrl::query()->count());
    }

    private function csvFile(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('links.csv', $contents);
    }
}
