<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        [, $this->token] = ApiKey::issueFor($this->admin, 'テスト');
    }

    public function test_requests_without_valid_key_are_rejected(): void
    {
        $this->getJson($this->apiUrl('/v1/links'))->assertUnauthorized()->assertJsonStructure(['message']);
        $this->withToken('chok_invalid')->getJson($this->apiUrl('/v1/links'))->assertUnauthorized();
    }

    public function test_keys_of_non_admins_and_revoked_keys_are_rejected(): void
    {
        [, $memberToken] = ApiKey::issueFor(User::factory()->create(), 'member');
        $this->withToken($memberToken)->getJson($this->apiUrl('/v1/links'))->assertForbidden();

        ApiKey::query()->update(['revoked_at' => now()]);
        $this->withToken($this->token)->getJson($this->apiUrl('/v1/links'))->assertUnauthorized();
    }

    public function test_admin_can_create_link_without_rate_limits(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->withToken($this->token)
                ->postJson($this->apiUrl('/v1/links'), ['url' => "https://example.com/{$i}"])
                ->assertCreated();
        }

        $this->assertSame(7, ShortUrl::query()->where('user_id', $this->admin->id)->count());
    }

    public function test_create_link_with_all_options(): void
    {
        $response = $this->withToken($this->token)->postJson($this->apiUrl('/v1/links'), [
            'url' => 'https://booth.pm/ja/items/1',
            'slug' => 'booth-item',
            'expires_at' => now()->addDays(10)->toIso8601String(),
            'password' => 'secret',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'booth-item')
            ->assertJsonPath('data.short_url', 'https://localhost/booth-item')
            ->assertJsonPath('data.password_protected', true)
            ->assertJsonPath('data.custom_slug', true);
        $this->assertStringStartsWith('data:image/svg+xml', (string) $response->json('data.qr_code'));
        $this->assertNotNull(ShortUrl::query()->where('slug', 'booth-item')->sole()->expires_at);
    }

    public function test_validation_errors_are_returned_as_json(): void
    {
        ShortUrl::factory()->custom('taken')->create();

        $this->withToken($this->token)->postJson($this->apiUrl('/v1/links'), ['url' => 'javascript:alert(1)'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        $this->withToken($this->token)->postJson($this->apiUrl('/v1/links'), ['url' => 'https://example.com/', 'slug' => 'taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        // Accept ヘッダーが無くても JSON で返す
        $this->withToken($this->token)->post($this->apiUrl('/v1/links'), ['url' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    public function test_list_show_and_delete_links(): void
    {
        $link = ShortUrl::factory()->for($this->admin)->custom('api-link')->create(['click_count' => 12]);
        ShortUrl::factory()->create();

        $this->withToken($this->token)->getJson($this->apiUrl('/v1/links'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $this->withToken($this->token)->getJson($this->apiUrl('/v1/links/api-link'))
            ->assertOk()
            ->assertJsonPath('data.click_count', 12);

        $this->withToken($this->token)->deleteJson($this->apiUrl('/v1/links/api-link'))->assertNoContent();
        $this->assertSoftDeleted($link);

        $this->withToken($this->token)->getJson($this->apiUrl('/v1/links/api-link'))->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_last_used_at_is_recorded(): void
    {
        $this->withToken($this->token)->getJson($this->apiUrl('/v1/links'))->assertOk();

        $this->assertNotNull(ApiKey::query()->sole()->last_used_at);
    }

    private function apiUrl(string $path): string
    {
        return 'http://'.config('shortener.domains.api').$path;
    }
}
