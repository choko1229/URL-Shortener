<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** お問い合わせ（フォーム・管理画面）と、利用規約・プライバシーポリシー */
final class ContactTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'このリンクを削除してほしいです。よろしくお願いします。';

    public function test_footer_links_to_the_contact_page(): void
    {
        $this->get($this->mainUrl())
            ->assertOk()
            ->assertSee('href="'.$this->mainUrl('/contact').'"', false)
            ->assertDontSee('（準備中）');
    }

    public function test_guest_can_send_an_inquiry_and_the_admin_is_notified(): void
    {
        AppSetting::store(AppSetting::DISCORD_WEBHOOK_URL, 'https://discord.com/api/webhooks/123/abcdef', encrypt: true);
        Http::fake(['discord.com/api/webhooks/*' => Http::response('', 204)]);

        $this->from($this->mainUrl('/contact'))
            ->post($this->mainUrl('/contact'), [
                'name' => 'テスト太郎',
                'reply_to' => 'taro@example.com',
                'message' => self::MESSAGE,
            ])
            ->assertRedirect($this->mainUrl('/contact'))
            ->assertSessionHas('notice');

        $inquiry = Inquiry::query()->sole();
        $this->assertSame('テスト太郎', $inquiry->name);
        $this->assertSame('taro@example.com', $inquiry->reply_to);
        $this->assertSame(self::MESSAGE, $inquiry->message);
        $this->assertNull($inquiry->user_id);
        $this->assertNull($inquiry->handled_at);

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), 'discord.com/api/webhooks')
            && str_contains((string) $request['content'], 'お問い合わせが届きました')
            && str_contains((string) $request['content'], self::MESSAGE)
            && $request['allowed_mentions'] === ['parse' => []]);
    }

    public function test_inquiry_from_a_logged_in_user_is_linked_to_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post($this->mainUrl('/contact'), ['message' => self::MESSAGE])->assertRedirect();

        $this->assertSame($user->id, Inquiry::query()->sole()->user_id);
    }

    public function test_short_messages_are_rejected(): void
    {
        $this->from($this->mainUrl('/contact'))
            ->post($this->mainUrl('/contact'), ['message' => '短い'])
            ->assertSessionHasErrors('message');

        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_recaptcha_protects_the_form_for_guests_only(): void
    {
        AppSetting::store(AppSetting::RECAPTCHA_SITE_KEY, 'site-key-abcdefghijklmnop');
        AppSetting::store(AppSetting::RECAPTCHA_PROJECT_ID, 'url-shortener-test');
        AppSetting::store(AppSetting::RECAPTCHA_API_KEY, 'api-key-abcdefghijklmnopqrst', encrypt: true);
        Http::fake(['recaptchaenterprise.googleapis.com/*' => Http::response([
            'tokenProperties' => ['valid' => true, 'action' => 'contact'],
            'riskAnalysis' => ['score' => 0.1],
        ])]);

        $this->get($this->mainUrl('/contact'))
            ->assertOk()
            ->assertSee('data-recaptcha-action="contact"', false);

        $this->from($this->mainUrl('/contact'))
            ->post($this->mainUrl('/contact'), ['message' => self::MESSAGE, 'recaptcha_token' => 'token'])
            ->assertSessionHas('error');
        $this->assertSame(0, Inquiry::query()->count());

        // ログイン中は判定しない
        $this->actingAs(User::factory()->create())->post($this->mainUrl('/contact'), ['message' => self::MESSAGE])->assertRedirect();
        $this->assertSame(1, Inquiry::query()->count());
        Http::assertSentCount(1);
    }

    public function test_admin_can_read_inquiries_and_mark_them_handled(): void
    {
        $admin = User::factory()->admin()->create();
        $inquiry = Inquiry::factory()->create(['message' => self::MESSAGE, 'name' => '送信者']);

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/inquiries'))
            ->assertOk()
            ->assertSee('送信者')
            ->assertSee(self::MESSAGE)
            ->assertSee('未対応 1 件');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/inquiries'))
            ->patch($this->dashboardUrl('/admin/inquiries/'.$inquiry->id), ['handled' => '1'])
            ->assertRedirect($this->dashboardUrl('/admin/inquiries'));
        $this->assertNotNull($inquiry->refresh()->handled_at);

        $this->actingAs($admin)->patch($this->dashboardUrl('/admin/inquiries/'.$inquiry->id), ['handled' => '0']);
        $this->assertNull($inquiry->refresh()->handled_at);
    }

    public function test_admin_can_publish_a_discord_contact(): void
    {
        $admin = User::factory()->admin()->create();

        $this->get($this->mainUrl('/contact'))->assertDontSee('Discord からもご連絡いただけます');

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/inquiries/contact'), ['discord_contact' => '@choko1229'])
            ->assertSessionHasNoErrors();

        $this->get($this->mainUrl('/contact'))
            ->assertSee('Discord からもご連絡いただけます')
            ->assertSee('@choko1229');

        $this->actingAs($admin)->put($this->dashboardUrl('/admin/inquiries/contact'), ['discord_contact' => '']);
        $this->get($this->mainUrl('/contact'))->assertDontSee('Discord からもご連絡いただけます');
    }

    public function test_members_cannot_read_inquiries(): void
    {
        User::factory()->admin()->create();
        $member = User::factory()->create();
        $inquiry = Inquiry::factory()->create();

        $this->actingAs($member)->get($this->dashboardUrl('/admin/inquiries'))->assertForbidden();
        $this->actingAs($member)->patch($this->dashboardUrl('/admin/inquiries/'.$inquiry->id), ['handled' => '1'])->assertForbidden();
        $this->actingAs($member)->put($this->dashboardUrl('/admin/inquiries/contact'), ['discord_contact' => '@x'])->assertForbidden();
    }
}
