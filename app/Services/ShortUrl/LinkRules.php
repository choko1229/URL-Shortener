<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\PreviewMode;
use App\Models\User;
use App\Rules\NotOwnDomain;
use App\Support\ShortenerSettings;
use Illuminate\Validation\Rule;

/** 発行時の入力ルール（API と CSV インポートで共用する） */
final class LinkRules
{
    /**
     * 各列は最初に見つかった問題だけを返す（bail）。「来月」に「日時として読めない」と「過去の日時」が両方出るような混乱を避ける
     *
     * @param  bool  $bulkImport  CSV インポート（管理者のみ）: スラッグの文字数の設定を無視し、1 文字から列の長さまで受け付ける
     * @return array<string, list<mixed>>
     */
    public static function basic(ShortenerSettings $settings, ?User $user, bool $bulkImport = false): array
    {
        $minLength = $bulkImport ? ShortenerSettings::ADMIN_CUSTOM_SLUG_MIN_LENGTH : $settings->customSlugMinLengthFor($user);
        $maxLength = $bulkImport ? ShortenerSettings::SLUG_COLUMN_LENGTH : $settings->customSlugMaxLength();

        return [
            'url' => ['bail', 'required', 'string', 'max:2048', 'url:http,https', new NotOwnDomain],
            'slug' => ['bail', 'nullable', 'string', 'min:'.$minLength, 'max:'.$maxLength, 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'expires_at' => ['bail', 'nullable', 'date', 'after:now'],
            'password' => ['bail', 'nullable', 'string', 'min:4', 'max:72'],
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
            'preview_mode' => ['bail', 'nullable', Rule::enum(PreviewMode::class)],
            'preview_title' => ['bail', 'exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'required', 'string', 'max:120'],
            'preview_description' => ['bail', 'exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'nullable', 'string', 'max:300'],
            'preview_image_url' => ['bail', 'exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * 日本語の検証メッセージ（アプリに日本語の言語ファイルが無いため、使う規則はすべてここで用意する）
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'url.required' => 'url が空です。短縮する URL を入れてください。',
            'url.string' => 'url は文字で入れてください。',
            'url.max' => 'url が長すぎます（:max文字まで）。',
            'url.url' => 'url は http:// または https:// から始まる URL を入れてください。',
            'slug.string' => 'slug は文字で入れてください。',
            'slug.min' => 'slug は:min文字以上にしてください。',
            'slug.max' => 'slug は:max文字以内にしてください。',
            'slug.regex' => 'slug に使えるのは半角英数字・ハイフン（-）・アンダースコア（_）だけです。',
            'expires_at.date' => 'expires_at を日時として読めません。2026-12-31 23:59 のように入れてください。',
            'expires_at.after' => 'expires_at が過去の日時です。現在より後の日時を入れてください。',
            'password.string' => 'password は文字で入れてください。',
            'password.min' => 'password は:min文字以上にしてください。',
            'password.max' => 'password は:max文字以内にしてください。',
            'preview_mode.enum' => 'preview_mode は destination・service・custom のいずれかを入れてください（空欄なら destination）。',
            'preview_title.required' => 'preview_mode を custom にした行は、preview_title（カードのタイトル）が必要です。',
            'preview_title.string' => 'preview_title は文字で入れてください。',
            'preview_title.max' => 'preview_title は:max文字以内にしてください。',
            'preview_description.string' => 'preview_description は文字で入れてください。',
            'preview_description.max' => 'preview_description は:max文字以内にしてください。',
            'preview_image_url.string' => 'preview_image_url は文字で入れてください。',
            'preview_image_url.max' => 'preview_image_url が長すぎます（:max文字まで）。',
            'preview_image_url.url' => 'preview_image_url は http:// または https:// から始まる URL を入れてください。',
        ];
    }
}
