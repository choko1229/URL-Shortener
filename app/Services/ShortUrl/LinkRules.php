<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\PreviewMode;
use App\Rules\NotOwnDomain;
use App\Support\ShortenerSettings;
use Illuminate\Validation\Rule;

/** 発行時の入力ルール（API と CSV インポートで共用する） */
final class LinkRules
{
    /** @return array<string, list<mixed>> */
    public static function basic(ShortenerSettings $settings): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', 'url:http,https', new NotOwnDomain],
            'slug' => ['nullable', 'string', 'min:'.$settings->customSlugMinLength(), 'max:'.$settings->customSlugMaxLength(), 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'min:4', 'max:72'],
        ];
    }

    /**
     * 共有時のカード。CSV インポートでのみ使う
     *
     * @return array<string, list<mixed>>
     */
    public static function preview(): array
    {
        return [
            'preview_mode' => ['nullable', Rule::enum(PreviewMode::class)],
            'preview_title' => ['exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'required', 'string', 'max:120'],
            'preview_description' => ['exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'nullable', 'string', 'max:300'],
            'preview_image_url' => ['exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'url.required' => 'url を指定してください。',
            'url.url' => 'url は http:// または https:// から始まる URL を指定してください。',
            'slug.regex' => 'slug には半角英数字・ハイフン・アンダースコアのみ使えます。',
            'slug.min' => 'slug は:min文字以上で指定してください。',
            'slug.max' => 'slug は:max文字以内で指定してください。',
            'expires_at.date' => 'expires_at は 2026-12-31 23:59 のような日時で指定してください。',
            'expires_at.after' => 'expires_at には現在より後の日時を指定してください。',
            'password.min' => 'password は:min文字以上で指定してください。',
            'preview_mode.enum' => 'preview_mode は destination / service / custom のいずれかで指定してください。',
            'preview_title.required' => 'preview_mode を custom にした行は preview_title が必要です。',
        ];
    }
}
