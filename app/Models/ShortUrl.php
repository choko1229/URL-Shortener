<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LinkStatus;
use App\Enums\SlugType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ShortUrlFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $slug
 * @property string $slug_normalized
 * @property SlugType $slug_type
 * @property string $original_url
 * @property string|null $password_hash
 * @property string|null $deletion_token_hash
 * @property CarbonImmutable|null $expires_at
 * @property int $click_count
 * @property CarbonImmutable|null $last_clicked_at
 * @property string|null $creator_ip_hash
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $deleted_at
 */
class ShortUrl extends Model
{
    /** @use HasFactory<ShortUrlFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'slug_type',
        'original_url',
        'expires_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password_hash',
        'deletion_token_hash',
        'creator_ip_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'slug_type' => SlugType::class,
            'expires_at' => 'immutable_datetime',
            'click_count' => 'integer',
            'last_clicked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /** slug を設定すると重複判定用の slug_normalized も同時に更新する */
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): array => [
                'slug' => $value,
                'slug_normalized' => mb_strtolower($value),
            ],
        );
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ShortUrlClick, $this> */
    public function clicks(): HasMany
    {
        return $this->hasMany(ShortUrlClick::class);
    }

    /** @param Builder<self> $query */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * 指定時点で期限切れでないもの
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveAt(Builder $query, CarbonInterface $at): void
    {
        $query->where(static function (Builder $q) use ($at): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', $at);
        });
    }

    public function isPasswordProtected(): bool
    {
        return $this->password_hash !== null;
    }

    public function isCustomSlug(): bool
    {
        return $this->slug_type === SlugType::Custom;
    }

    public function isExpiredAt(CarbonInterface $at): bool
    {
        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo($at);
    }

    public function statusAt(CarbonInterface $at, int $warningDays): LinkStatus
    {
        if ($this->expires_at === null) {
            return LinkStatus::Active;
        }

        if ($this->expires_at->lessThanOrEqualTo($at)) {
            return LinkStatus::Expired;
        }

        return $this->expires_at->lessThanOrEqualTo($at->copy()->addDays($warningDays))
            ? LinkStatus::ExpiringSoon
            : LinkStatus::Active;
    }
}
