<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ExpiryOption;
use App\Rules\NotOwnDomain;
use App\Services\ShortUrl\ShortUrlDraft;
use App\Support\ShortenerSettings;
use App\ViewModels\ShortUrlFormData;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 短縮URL発行フォームの入力検証（形式のみ）。
 * 予約語・スラッグの重複・発行上限は DB を参照するため ShortUrlIssuer で確認する。
 */
final class StoreShortUrlRequest extends FormRequest
{
    private const PASSWORD_MIN_LENGTH = 4;

    // bcrypt が扱える最大バイト長
    private const PASSWORD_MAX_LENGTH = 72;

    private const ORIGINAL_URL_MAX_LENGTH = 2048;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $settings = $this->container->make(ShortenerSettings::class);
        $isMember = $this->user() !== null;

        return [
            'original_url' => ['required', 'string', 'max:'.self::ORIGINAL_URL_MAX_LENGTH, 'url:http,https', new NotOwnDomain],
            'custom_slug' => $isMember
                ? [
                    'nullable',
                    'string',
                    'min:'.$settings->customSlugMinLength(),
                    'max:'.$settings->customSlugMaxLength(),
                    'regex:/\A[A-Za-z0-9_-]+\z/',
                ]
                : ['prohibited'],
            'expiry' => ['required', 'string', Rule::in($this->allowedExpiryValues($isMember, $settings))],
            'expires_at' => [
                'exclude_unless:expiry,'.ExpiryOption::Custom->value,
                'required',
                'date_format:'.ShortUrlFormData::DATETIME_LOCAL_FORMAT,
                $this->expiresAtRule($isMember, $settings),
            ],
            'password' => ['nullable', 'string', 'min:'.self::PASSWORD_MIN_LENGTH, 'max:'.self::PASSWORD_MAX_LENGTH],
            // reCAPTCHA v3 のトークン（検証はコントローラで行う）
            'recaptcha_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function toDraft(): ShortUrlDraft
    {
        $customSlug = $this->validated('custom_slug');
        $expiresAt = $this->validated('expires_at');
        $password = $this->validated('password');

        return new ShortUrlDraft(
            originalUrl: (string) $this->validated('original_url'),
            customSlug: is_string($customSlug) && $customSlug !== '' ? $customSlug : null,
            expiry: ExpiryOption::from((string) $this->validated('expiry')),
            expiresAtLocal: is_string($expiresAt) ? $expiresAt : null,
            password: is_string($password) && $password !== '' ? $password : null,
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'original_url.required' => '短縮したいURLを入力してください。',
            'original_url.url' => 'http:// または https:// から始まるURLを入力してください。',
            'original_url.max' => 'URLは'.self::ORIGINAL_URL_MAX_LENGTH.'文字以内で入力してください。',
            'custom_slug.prohibited' => 'カスタムスラッグはログインすると利用できます。',
            'custom_slug.min' => 'カスタムスラッグは:min文字以上で入力してください。',
            'custom_slug.max' => 'カスタムスラッグは:max文字以内で入力してください。',
            'custom_slug.regex' => 'カスタムスラッグには半角英数字・ハイフン・アンダースコアのみ使えます。',
            'expiry.required' => '有効期限を選択してください。',
            'expiry.in' => $this->user() === null
                ? '無期限にするにはログインが必要です。ログインしない場合は期限を選択してください。'
                : '有効期限の選択肢が正しくありません。',
            'expires_at.required' => '有効期限の日時を入力してください。',
            'expires_at.date_format' => '有効期限の日時の形式が正しくありません。',
            'password.min' => 'パスワードは:min文字以上で入力してください。',
            'password.max' => 'パスワードは:max文字以内で入力してください。',
        ];
    }

    /** @return list<string> */
    private function allowedExpiryValues(bool $isMember, ShortenerSettings $settings): array
    {
        $options = $isMember
            ? ExpiryOption::cases()
            : array_filter(
                ExpiryOption::selectableWithin($settings->guestMaxExpiryDays()),
                static fn (ExpiryOption $option): bool => $option !== ExpiryOption::Never,
            );

        return array_values(array_map(static fn (ExpiryOption $option): string => $option->value, $options));
    }

    /** datetime-local の値は利用者のローカル時刻（表示タイムゾーン）として解釈する */
    private function expiresAtRule(bool $isMember, ShortenerSettings $settings): Closure
    {
        $timezone = $settings->displayTimezone();
        $maxDays = $isMember ? null : $settings->guestMaxExpiryDays();

        return static function (string $attribute, mixed $value, Closure $fail) use ($timezone, $maxDays): void {
            if (! is_string($value)) {
                return;
            }

            $expiresAt = CarbonImmutable::createFromFormat(ShortUrlFormData::DATETIME_LOCAL_FORMAT, $value, $timezone);
            if (! $expiresAt instanceof CarbonImmutable) {
                return;
            }

            $now = CarbonImmutable::now($timezone);
            if ($expiresAt->lessThanOrEqualTo($now)) {
                $fail('有効期限には現在より後の日時を指定してください。');

                return;
            }

            if ($maxDays !== null && $expiresAt->greaterThan($now->addDays($maxDays))) {
                $fail("ログインしていない場合、有効期限は{$maxDays}日以内で指定してください。");
            }
        };
    }
}
