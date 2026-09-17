<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->dashboardUrl())
            ->assertRedirect($this->mainUrl('/login'));
    }

    public function test_member_sees_own_stats_and_links_only(): void
    {
        $user = User::factory()->create(['global_name' => 'ちょこ']);
        ShortUrl::factory()->for($user)->custom('kanri-memo')->expiresIn(2)->passwordProtected()->create(['click_count' => 58]);
        ShortUrl::factory()->for($user)->expired()->create(['click_count' => 9]);
        ShortUrl::factory()->custom('someone-else')->create();

        $response = $this->actingAs($user)->get($this->dashboardUrl());

        $response->assertOk()
            ->assertSee('すぐに短縮URLを発行')
            ->assertSee('今月あと 58 件発行できます（60件/月まで）')
            ->assertSee('localhost/kanri-memo')
            ->assertSee('残り')
            ->assertSee('期限切れ')
            ->assertSee('パスワード保護あり')
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertDontSee('someone-else')
            ->assertDontSee('APIキー')
            ->assertSee('ちょこ');
    }

    public function test_admin_sees_admin_navigation(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get($this->dashboardUrl())
            ->assertOk()
            ->assertSee('APIキー')
            ->assertSee('予約語')
            ->assertSee('管理者');
    }

    public function test_member_cannot_delete_others_link(): void
    {
        $link = ShortUrl::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete($this->dashboardUrl("/links/{$link->id}"))
            ->assertForbidden();
    }

    public function test_logout_requires_post_and_ends_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($this->dashboardUrl('/logout'))->assertStatus(405);

        $this->actingAs($user)
            ->post($this->dashboardUrl('/logout'))
            ->assertRedirect(route('main.home'));

        $this->assertGuest();
    }
}
