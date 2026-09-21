<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\NotOwnDomain;
use App\ViewModels\ShortUrlFormData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** 管理者による短縮URLの編集（元URL・有効期限・発行者） */
final class UpdateLinkRequest extends FormRequest
{
    public const NEVER = 'never';

    public const CUSTOM = 'custom';

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'original_url' => ['bail', 'required', 'string', 'max:2048', 'url:http,https', new NotOwnDomain],
            'expiry' => ['required', Rule::in([self::NEVER, self::CUSTOM])],
            // 管理者は過去の日時も指定できる（削除せずに期限切れにする場合）
            'expires_at' => ['exclude_unless:expiry,'.self::CUSTOM, 'required', 'date_format:'.ShortUrlFormData::DATETIME_LOCAL_FORMAT],
            // 空なら「未ログインで発行」扱いにする
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'original_url.required' => '元URLを入力してください。',
            'original_url.max' => '元URLは:max文字以内で入力してください。',
            'original_url.url' => '元URLは http:// または https:// から始まる URL を入力してください。',
            'expiry.required' => '有効期限を選んでください。',
            'expires_at.required' => '有効期限の日時を入力してください。',
            'expires_at.date_format' => '有効期限の日時が正しくありません。',
            'user_id.exists' => '選んだユーザーが見つかりません（退会した可能性があります）。',
        ];
    }

    /** @return array{original_url: string, expires_at_local: string|null, user_id: int|null} */
    public function changes(): array
    {
        $userId = $this->validated('user_id');

        return [
            'original_url' => $this->string('original_url')->trim()->toString(),
            'expires_at_local' => $this->validated('expiry') === self::CUSTOM ? (string) $this->validated('expires_at') : null,
            'user_id' => $userId === null || $userId === '' ? null : (int) $userId,
        ];
    }
}
