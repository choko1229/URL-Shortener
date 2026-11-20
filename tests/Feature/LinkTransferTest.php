<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PreviewMode;
use App\Enums\ReservedWordCategory;
use App\Enums\SlugType;
use App\Http\Controllers\Dashboard\LinkTransferController;
use App\Models\ReservedWord;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\ShortUrl\Transfer\LinkTransferResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** 発行履歴の CSV エクスポート・インポート（別のサーバーへの移行） */
final class LinkTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_export_and_import_buttons(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->dashboardUrl('/'))
            ->assertOk()
            ->assertSee('エクスポート')
            ->assertSee('インポート')
            ->assertSee($this->dashboardUrl('/links/export'), escape: false)
            ->assertSee('link-import-dialog');
    }

    public function test_export_contains_only_own_links_with_slug_and_password_hash(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->custom('spring-sale')->passwordProtected()->create([
            'original_url' => 'https://example.com/a',
            'expires_at' => '2030-01-01 00:00:00',
            'click_count' => 12,
        ]);
        ShortUrl::factory()->for($user)->create(['slug' => 'aB3xQ9k', 'original_url' => 'https://example.com/b']);
        ShortUrl::factory()->custom('someone-else')->create();
        ShortUrl::factory()->for($user)->custom('deleted-one')->create()->delete();

        $response = $this->actingAs($user)
            ->get($this->dashboardUrl('/links/export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('short-urls-', (string) $response->headers->get('Content-Disposition'));

        $csv = (string) $response->getContent();
        $this->assertStringStartsWith("\u{FEFF}slug,domain,short_url,url,slug_type,expires_at,password_hash", $csv);
        $this->assertStringContainsString('spring-sale', $csv);
        $this->assertStringContainsString('aB3xQ9k', $csv);
        $this->assertStringContainsString('2030-01-01 09:00:00+09:00', $csv);
        $this->assertStringContainsString((string) ShortUrl::query()->where('slug', 'spring-sale')->value('password_hash'), $csv);
        $this->assertStringNotContainsString('someone-else', $csv);
        $this->assertStringNotContainsString('deleted-one', $csv);
    }

    public function test_exported_csv_can_be_imported_on_another_server_with_the_same_urls(): void
    {
        $source = User::factory()->create();
        ShortUrl::factory()->for($source)->custom('spring-sale')->passwordProtected()->create([
            'original_url' => 'https://example.com/a',
            'click_count' => 12,
        ]);
        ShortUrl::factory()->for($source)->create(['slug' => 'aB3xQ9k', 'original_url' => 'https://example.com/b']);
        ShortUrl::factory()->for($source)->custom('event')->create([
            'original_url' => 'https://example.com/c',
            'preview_mode' => PreviewMode::Custom,
            'preview_title' => '秋のイベント',
            'preview_description' => "1行目\n2行目, カンマ入り",
        ]);

        $csv = (string) $this->actingAs($source)->get($this->dashboardUrl('/links/export'))->getContent();

        // 移行先のサーバー（空のデータベース）を再現する
        ShortUrl::query()->forceDelete();
        $target = User::factory()->create();

        $this->actingAs($target)
            ->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile($csv)])
            ->assertRedirect()
            ->assertSessionHas('notice', fn (string $message): bool => str_contains($message, '3件を取り込みました'));

        $this->assertSame(3, ShortUrl::query()->where('user_id', $target->id)->count());

        $first = ShortUrl::query()->where('slug', 'spring-sale')->sole();
        $this->assertSame('https://example.com/a', $first->original_url);
        $this->assertSame(SlugType::Custom, $first->slug_type);
        $this->assertSame(12, $first->click_count);
        $this->assertTrue(Hash::check('password', (string) $first->password_hash));

        $this->assertSame(SlugType::Random, ShortUrl::query()->where('slug', 'aB3xQ9k')->sole()->slug_type);

        $event = ShortUrl::query()->where('slug', 'event')->sole();
        $this->assertSame(PreviewMode::Custom, $event->preview_mode);
        $this->assertSame("1行目\n2行目, カンマ入り", $event->preview_description);

        // 同じ短縮URLで開ける
        $this->get($this->mainUrl('/event'))->assertOk();
    }

    public function test_conflicting_urls_are_not_imported_until_the_user_decides(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->custom('mine')->create(['original_url' => 'https://example.com/old']);
        ShortUrl::factory()->custom('theirs')->create(['original_url' => 'https://example.com/theirs']);

        $csv = <<<'CSV'
        slug,url,slug_type
        mine,https://example.com/new,custom
        theirs,https://example.com/x,custom
        fresh,https://example.com/fresh,custom
        CSV;

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile($csv)])
            ->assertRedirect()
            ->assertSessionHas(LinkTransferController::PENDING_SESSION_KEY, function (array $pending): bool {
                return $pending['ready'] === 1
                    && count($pending['conflicts']) === 2
                    && $pending['conflicts'][0]['kind'] === 'own'
                    && $pending['conflicts'][1]['kind'] === 'taken';
            });

        // 確認するまでは何も取り込まない
        $this->assertFalse(ShortUrl::query()->where('slug', 'fresh')->exists());
        $this->assertSame('https://example.com/old', ShortUrl::query()->where('slug', 'mine')->value('original_url'));

        $this->actingAs($user)
            ->get($this->dashboardUrl('/'))
            ->assertOk()
            ->assertSee('同じ短縮URLがすでに使われています（2件）')
            ->assertSee('CSV の内容で上書き')
            ->assertSee('https://example.com/old');
    }

    public function test_user_can_overwrite_own_link_and_reissue_others(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->custom('mine')->create(['original_url' => 'https://example.com/old', 'click_count' => 5]);
        ShortUrl::factory()->custom('theirs')->create(['original_url' => 'https://example.com/theirs']);

        $csv = "slug,url,slug_type\nmine,https://example.com/new,custom\ntheirs,https://example.com/x,custom\nfresh,https://example.com/fresh,custom\n";

        $token = $this->startImport($user, $csv);

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import/confirm'), [
                'token' => $token,
                'resolutions' => [2 => 'overwrite', 3 => 'new'],
            ])
            ->assertRedirect()
            ->assertSessionMissing(LinkTransferController::PENDING_SESSION_KEY)
            ->assertSessionHas(LinkTransferController::RESULT_SESSION_KEY, fn (LinkTransferResult $r): bool => $r->imported === 1 && $r->overwritten === 1 && $r->reissued === 1);

        $mine = ShortUrl::query()->where('slug', 'mine')->sole();
        $this->assertSame('https://example.com/new', $mine->original_url);
        // 上書きしてもクリック数はこのサーバーのものを残す
        $this->assertSame(5, $mine->click_count);

        // ほかの人の短縮URLはそのまま
        $this->assertSame('https://example.com/theirs', ShortUrl::query()->where('slug', 'theirs')->value('original_url'));
        $reissued = ShortUrl::query()->where('user_id', $user->id)->where('original_url', 'https://example.com/x')->sole();
        $this->assertNotSame('theirs', $reissued->slug);
        $this->assertSame(SlugType::Random, $reissued->slug_type);

        $this->assertTrue(ShortUrl::query()->where('slug', 'fresh')->where('user_id', $user->id)->exists());
    }

    public function test_others_links_cannot_be_overwritten_even_if_requested(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->custom('theirs')->create(['original_url' => 'https://example.com/theirs']);

        $token = $this->startImport($user, "slug,url\ntheirs,https://example.com/x\n");

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import/confirm'), ['token' => $token, 'resolutions' => [2 => 'overwrite']])
            ->assertRedirect();

        $this->assertSame('https://example.com/theirs', ShortUrl::query()->where('slug', 'theirs')->value('original_url'));
        $this->assertSame(0, ShortUrl::query()->where('user_id', $user->id)->count());
    }

    public function test_unanswered_conflicts_are_skipped(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->custom('mine')->create(['original_url' => 'https://example.com/old']);

        $token = $this->startImport($user, "slug,url\nmine,https://example.com/new\n");

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import/confirm'), ['token' => $token])
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, '1件をスキップしました'));

        $this->assertSame('https://example.com/old', ShortUrl::query()->where('slug', 'mine')->value('original_url'));
    }

    public function test_user_can_cancel_the_import(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->custom('mine')->create();

        $token = $this->startImport($user, "slug,url\nmine,https://example.com/new\nfresh,https://example.com/fresh\n");

        $this->actingAs($user)
            ->delete($this->dashboardUrl('/links/import'))
            ->assertRedirect()
            ->assertSessionMissing(LinkTransferController::PENDING_SESSION_KEY);

        $this->assertFalse(ShortUrl::query()->where('slug', 'fresh')->exists());

        // やめたあとは確認の続きもできない
        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import/confirm'), ['token' => $token])
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, '有効期限が切れました'));
        $this->assertFalse(ShortUrl::query()->where('slug', 'fresh')->exists());
    }

    public function test_deleted_codes_and_case_insensitive_random_codes_are_conflicts(): void
    {
        $user = User::factory()->create();
        ShortUrl::factory()->for($user)->custom('gone')->create()->delete();
        ShortUrl::factory()->create(['slug' => 'AbCdEfG']);

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile("slug,url,slug_type\ngone,https://example.com/a,custom\nabcdefg,https://example.com/b,random\n")])
            ->assertSessionHas(LinkTransferController::PENDING_SESSION_KEY, function (array $pending): bool {
                return count($pending['conflicts']) === 2
                    && $pending['conflicts'][0]['kind'] === 'taken'
                    && $pending['conflicts'][1]['kind'] === 'taken';
            });
    }

    public function test_reserved_words_are_conflicts_for_members_but_not_admins(): void
    {
        ReservedWord::query()->create(['word' => 'admin', 'category' => ReservedWordCategory::System]);
        $csv = "slug,url\nadmin,https://example.com/a\n";

        $this->actingAs(User::factory()->create())
            ->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile($csv)])
            ->assertSessionHas(LinkTransferController::PENDING_SESSION_KEY);

        $this->flushSession();

        $this->actingAs(User::factory()->admin()->create())
            ->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile($csv)])
            ->assertSessionMissing(LinkTransferController::PENDING_SESSION_KEY)
            ->assertSessionHas('notice');

        $this->assertTrue(ShortUrl::query()->where('slug', 'admin')->exists());
    }

    public function test_invalid_rows_are_reported_and_valid_rows_are_imported(): void
    {
        $user = User::factory()->create();

        $csv = "slug,url,password_hash\nok-one,https://example.com/a,\nbad-url,not a url,\nbad-hash,https://example.com/c,plain-text\nok-one,https://example.com/d,\n";

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile($csv)])
            ->assertSessionHas(LinkTransferController::RESULT_SESSION_KEY, function (LinkTransferResult $result): bool {
                return $result->imported === 1 && $result->failedLines() === [3, 4, 5];
            });

        $this->assertSame(1, ShortUrl::query()->where('user_id', $user->id)->count());
    }

    public function test_csv_without_url_column_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile("link,slug\nhttps://example.com/a,\n")])
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'url がありません'));
    }

    public function test_members_cannot_exceed_the_monthly_limit_by_importing(): void
    {
        config(['shortener.defaults.member_monthly_limit' => 2]);
        $user = User::factory()->create();

        $csv = "slug,url\na-one,https://example.com/1\na-two,https://example.com/2\na-three,https://example.com/3\n";

        $this->actingAs($user)
            ->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile($csv)])
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, '今月はあと2件'));

        $this->assertSame(0, ShortUrl::query()->count());
    }

    public function test_guests_cannot_export_or_import(): void
    {
        $this->get($this->dashboardUrl('/links/export'))->assertRedirect();
        $this->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile("url\nhttps://example.com/a\n")])->assertRedirect();
        $this->assertSame(0, ShortUrl::query()->count());
    }

    private function startImport(User $user, string $csv): string
    {
        $this->actingAs($user)->post($this->dashboardUrl('/links/import'), ['file' => $this->csvFile($csv)]);

        $pending = session(LinkTransferController::PENDING_SESSION_KEY);
        $this->assertIsArray($pending);

        return $pending['token'];
    }

    private function csvFile(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('links.csv', $contents);
    }
}
