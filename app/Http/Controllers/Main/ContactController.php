<?php

declare(strict_types=1);

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInquiryRequest;
use App\Models\AppSetting;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Security\RecaptchaVerifier;
use App\Services\Update\DiscordWebhookNotifier;
use App\ViewModels\ViewerData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * お問い合わせ。メール送信の設定が無くても受け取れるよう、内容をデータベースに保存し、
 * Discord Webhook が設定されていれば管理者へ通知する（管理画面の「お問い合わせ」で読める）。
 */
final class ContactController extends Controller
{
    public function show(Request $request, RecaptchaVerifier $recaptcha): View
    {
        $user = self::user($request);

        return view('main.contact', [
            'viewer' => $user !== null ? ViewerData::fromUser($user) : ViewerData::guest(),
            'discordContact' => self::discordContact(),
            // 未ログインの送信のみスパム対策を適用する（requirements.md 4-4 と同じ扱い）
            'recaptchaSiteKey' => $user === null ? $recaptcha->siteKey() : null,
        ]);
    }

    public function store(StoreInquiryRequest $request, RecaptchaVerifier $recaptcha, DiscordWebhookNotifier $notifier): RedirectResponse
    {
        $user = self::user($request);

        if ($user === null && ! $recaptcha->verify(RecaptchaVerifier::ACTION_CONTACT, $request->validated('recaptcha_token'), $request->ip(), $request->userAgent())) {
            return back()
                ->withInput($request->safe()->except('recaptcha_token'))
                ->with('error', 'スパム対策の確認に失敗しました。ページを再読み込みして、もう一度お試しください。');
        }

        $inquiry = new Inquiry($request->safe()->only(['name', 'reply_to', 'message']));
        $inquiry->user_id = $user?->id;
        $inquiry->save();

        Log::info('お問い合わせを受け付けました。', ['inquiry_id' => $inquiry->id, 'user_id' => $user?->id]);

        $notifier->send(sprintf(
            "%sお問い合わせが届きました（#%d）\n送信者: %s / 返信先: %s\n%s\n%s",
            $notifier->prefix(),
            $inquiry->id,
            $inquiry->senderLabel(),
            $inquiry->reply_to ?? '未入力',
            mb_strimwidth($inquiry->message, 0, 500, '…'),
            route('dashboard.admin.inquiries'),
        ));

        return redirect()
            ->route('main.contact')
            ->with('notice', 'お問い合わせを受け付けました。返信が必要な場合は、内容を確認のうえご連絡します。');
    }

    /** 公開する Discord の連絡先（管理画面で設定する。未設定ならフォームのみ案内する） */
    private static function discordContact(): ?string
    {
        $value = AppSetting::valueFor(AppSetting::CONTACT_DISCORD);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
