<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ダッシュボード・管理者の一覧の並べ替え */
final class LinkSortTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        ShortUrl::factory()->for($this->user)->custom('bbb-link')->create(['click_count' => 5, 'expires_at' => now()->addDays(30)]);
        ShortUrl::factory()->for($this->user)->custom('aaa-link')->create(['click_count' => 50, 'expires_at' => null]);
        ShortUrl::factory()->for($this->user)->custom('ccc-link')->create(['click_count' => 1, 'expires_at' => now()->addDays(3)]);
    }

    public function test_default_order_is_newest_first(): void
    {
        $this->actingAs($this->user)
            ->get($this->dashboardUrl())
            ->assertOk()
            ->assertSeeInOrder(['localhost/ccc-link', 'localhost/aaa-link', 'localhost/bbb-link'])
            ->assertSee('aria-sort="descending"', false);
    }

    public function test_sorts_by_clicks(): void
    {
        $this->actingAs($this->user)
            ->get($this->dashboardUrl('/?sort=clicks&dir=desc'))
            ->assertSeeInOrder(['localhost/aaa-link', 'localhost/bbb-link', 'localhost/ccc-link']);

        $this->actingAs($this->user)
            ->get($this->dashboardUrl('/?sort=clicks&dir=asc'))
            ->assertSeeInOrder(['localhost/ccc-link', 'localhost/bbb-link', 'localhost/aaa-link']);
    }

    public function test_sorts_by_slug(): void
    {
        $this->actingAs($this->user)
            ->get($this->dashboardUrl('/?sort=slug'))
            ->assertSeeInOrder(['localhost/aaa-link', 'localhost/bbb-link', 'localhost/ccc-link']);
    }

    public function test_never_expiring_links_come_last_when_sorted_by_nearest_expiry(): void
    {
        $this->actingAs($this->user)
            ->get($this->dashboardUrl('/?sort=expires&dir=asc'))
            ->assertSeeInOrder(['localhost/ccc-link', 'localhost/bbb-link', 'localhost/aaa-link']);

        $this->actingAs($this->user)
            ->get($this->dashboardUrl('/?sort=expires&dir=desc'))
            ->assertSeeInOrder(['localhost/aaa-link', 'localhost/bbb-link', 'localhost/ccc-link']);
    }

    public function test_unknown_values_fall_back_to_default(): void
    {
        $this->actingAs($this->user)
            ->get($this->dashboardUrl('/?sort=original_url;drop&dir=sideways'))
            ->assertOk()
            ->assertSeeInOrder(['localhost/ccc-link', 'localhost/aaa-link', 'localhost/bbb-link']);
    }

    public function test_header_link_toggles_direction_and_pagination_keeps_sort(): void
    {
        ShortUrl::factory()->for($this->user)->count(30)->create();

        $response = $this->actingAs($this->user)->get($this->dashboardUrl('/?sort=clicks&dir=desc'));

        // 表示中の列はもう一度押すと逆順
        $response->assertSee('sort=clicks&amp;dir=asc', false)
            // ページ送りでも並び順を保つ
            ->assertSee('page=2', false)
            ->assertSee('sort=clicks&amp;dir=desc&amp;page=2', false);
    }

    public function test_admin_list_can_be_sorted(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get($this->dashboardUrl('/admin/links?sort=clicks&dir=desc'))
            ->assertOk()
            ->assertSeeInOrder(['localhost/aaa-link', 'localhost/bbb-link', 'localhost/ccc-link'])
            // 絞り込みのフォームでも並び順を引き継ぐ
            ->assertSee('<input type="hidden" name="sort" value="clicks">', false);
    }

    public function test_expiring_soon_row_shows_the_actual_date(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Tokyo')->setDate(2026, 9, 27)->setTime(12, 0));
        ShortUrl::factory()->for($this->user)->custom('soon-link')->create(['expires_at' => now()->addDays(2)]);

        $this->actingAs($this->user)
            ->get($this->dashboardUrl())
            ->assertSee('残り2日')
            ->assertSee('2026/09/29 12:00 まで');
    }
}
