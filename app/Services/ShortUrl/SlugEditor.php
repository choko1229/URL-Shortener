<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\SlugType;
use App\Models\RetiredSlug;
use App\Models\ShortUrl;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * カスタムスラッグの編集（requirements.md 2-4: 編集できるのはカスタムスラッグのみ）。
 * 変更前のコードは欠番として記録し、以後は使えない・再利用できない。
 */
final class SlugEditor
{
    public function __construct(private readonly ShortUrlIssuer $issuer) {}

    /** @throws IssuanceException */
    public function change(ShortUrl $link, string $newSlug, User $editor): void
    {
        if ($link->slug === $newSlug) {
            return;
        }

        $this->issuer->ensureCustomSlugAvailable($newSlug, $editor);

        $oldSlug = $link->slug;
        $oldType = $link->slug_type;

        try {
            DB::transaction(static function () use ($link, $newSlug, $oldSlug, $oldType): void {
                RetiredSlug::query()->create([
                    'slug' => $oldSlug,
                    'slug_type' => $oldType,
                    'short_url_id' => $link->id,
                    'retired_at' => CarbonImmutable::now(),
                ]);

                $link->slug = $newSlug;
                $link->slug_type = SlugType::Custom;
                $link->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw new IssuanceException('このカスタムスラッグはすでに使われています。', 'custom_slug');
        }

        Log::info('カスタムスラッグを変更しました。', [
            'short_url_id' => $link->id,
            'user_id' => $editor->id,
        ]);
    }
}
