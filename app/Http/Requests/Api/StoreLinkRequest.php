<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\NotOwnDomain;
use App\Services\ShortUrl\ShortUrlDraft;
use App\Support\ShortenerSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** API での短縮URL発行（requirements.md 5） */
final class StoreLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $settings = $this->container->make(ShortenerSettings::class);

        return [
            'url' => ['required', 'string', 'max:2048', 'url:http,https', new NotOwnDomain],
            'slug' => ['nullable', 'string', 'min:'.$settings->customSlugMinLength(), 'max:'.$settings->customSlugMaxLength(), 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'min:4', 'max:72'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'url.required' => 'url を指定してください。',
            'url.url' => 'url は http:// または https:// から始まる URL を指定してください。',
            'slug.regex' => 'slug には半角英数字・ハイフン・アンダースコアのみ使えます。',
            'expires_at.date' => 'expires_at は ISO 8601 形式の日時で指定してください。',
            'expires_at.after' => 'expires_at には現在より後の日時を指定してください。',
        ];
    }

    public function toDraft(): ShortUrlDraft
    {
        $slug = $this->validated('slug');
        $expiresAt = $this->validated('expires_at');
        $password = $this->validated('password');

        return ShortUrlDraft::withExpiresAt(
            originalUrl: (string) $this->validated('url'),
            customSlug: is_string($slug) && $slug !== '' ? $slug : null,
            // タイムゾーンの指定が無い日時は表示タイムゾーン（日本時間）として扱う
            expiresAt: is_string($expiresAt) && $expiresAt !== ''
                ? CarbonImmutable::parse($expiresAt, $this->container->make(ShortenerSettings::class)->displayTimezone())
                : null,
            password: is_string($password) && $password !== '' ? $password : null,
        );
    }
}
