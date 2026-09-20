<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Services\ShortUrl\LinkRules;
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
        return LinkRules::basic($this->container->make(ShortenerSettings::class));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return LinkRules::messages() + [
            'expires_at.date' => 'expires_at は ISO 8601 形式の日時で指定してください。',
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
