<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_home_page_with_shorten_form(): void
    {
        $response = $this->get($this->mainUrl());

        $response->assertOk()
            ->assertSee('長いURLを、', false)
            ->assertSee('name="_token"', false)
            ->assertSee('<label for="home-original-url"', false)
            ->assertSee('data-requires-login="login-dialog"', false)
            ->assertSee('id="login-dialog"', false)
            ->assertDontSee('name="custom_slug"', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_member_sees_custom_slug_field_and_dashboard_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->mainUrl())
            ->assertOk()
            ->assertSee('name="custom_slug"', false)
            ->assertSee('ダッシュボード')
            ->assertDontSee('id="login-dialog"', false);
    }

    public function test_guest_cannot_choose_never_expiry_or_custom_slug(): void
    {
        $this->from($this->mainUrl())
            ->post($this->mainUrl('/shorten'), [
                'original_url' => 'https://example.com/path',
                'expiry' => 'never',
                'custom_slug' => 'my-link',
            ])
            ->assertRedirect($this->mainUrl())
            ->assertSessionHasErrors(['expiry', 'custom_slug']);
    }

    public function test_invalid_scheme_is_rejected(): void
    {
        $this->from($this->mainUrl())
            ->post($this->mainUrl('/shorten'), [
                'original_url' => 'javascript:alert(1)',
                'expiry' => '7d',
            ])
            ->assertSessionHasErrors(['original_url']);
    }

    public function test_valid_request_returns_not_implemented_notice_without_flashing_password(): void
    {
        $this->from($this->mainUrl())
            ->post($this->mainUrl('/shorten'), [
                'original_url' => 'https://example.com/path',
                'expiry' => '7d',
                'password' => 'secret-pass',
            ])
            ->assertRedirect($this->mainUrl())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_user_supplied_url_is_escaped_when_re_rendered(): void
    {
        $this->withSession(['_old_input' => ['original_url' => '"><script>alert(1)</script>']])
            ->get($this->mainUrl())
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
