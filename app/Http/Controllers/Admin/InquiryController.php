<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Inquiry;
use App\Support\ShortenerSettings;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** 届いたお問い合わせの確認（requirements.md 4-2 管理者の特権） */
final class InquiryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request, ShortenerSettings $settings): View
    {
        return view('dashboard.admin.inquiries', [
            'viewer' => ViewerData::fromUser($request->user()),
            'inquiries' => Inquiry::query()->with('user')->latest('id')->paginate(self::PER_PAGE)->withQueryString(),
            'unhandledCount' => Inquiry::query()->whereNull('handled_at')->count(),
            'discordContact' => (string) (AppSetting::valueFor(AppSetting::CONTACT_DISCORD) ?? ''),
            'timezone' => $settings->displayTimezone(),
        ]);
    }

    /** 対応済み・未対応を切り替える */
    public function updateStatus(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $handled = $request->boolean('handled');

        $inquiry->handled_at = $handled ? CarbonImmutable::now() : null;
        $inquiry->save();

        return back()->with('notice', $handled ? '対応済みにしました。' : '未対応に戻しました。');
    }

    /** お問い合わせページに公開する Discord の連絡先 */
    public function updateContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // ユーザー名（@name）や招待リンクを想定。改行などの制御文字は入れられない
            'discord_contact' => ['nullable', 'string', 'max:100', 'regex:/\A[^\x00-\x1F\x7F]*\z/'],
        ], [
            'discord_contact.max' => 'Discord の連絡先は:max文字以内で入力してください。',
            'discord_contact.regex' => 'Discord の連絡先に改行などの制御文字は使えません。',
        ]);

        $contact = trim($validated['discord_contact'] ?? '');

        $contact === ''
            ? AppSetting::query()->where('key', AppSetting::CONTACT_DISCORD)->delete()
            : AppSetting::store(AppSetting::CONTACT_DISCORD, $contact);

        Log::notice('お問い合わせページの Discord 連絡先を変更しました。', ['user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', 'お問い合わせページの連絡先を保存しました。');
    }
}
