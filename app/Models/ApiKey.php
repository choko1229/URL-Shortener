<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $key_prefix
 * @property string $key_hash
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 */
class ApiKey extends Model
{
    public const TOKEN_PREFIX = 'chok_';

    private const RANDOM_LENGTH = 40;

    /** @var list<string> */
    protected $fillable = [
        'name',
    ];

    /** @var list<string> */
    protected $hidden = [
        'key_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * キーを発行する。平文のトークンは戻り値でしか得られない
     *
     * @return array{0: self, 1: string}
     */
    public static function issueFor(User $user, string $name): array
    {
        $token = self::TOKEN_PREFIX.Str::random(self::RANDOM_LENGTH);

        $key = new self(['name' => $name]);
        $key->user_id = $user->id;
        $key->key_prefix = substr($token, 0, 12);
        $key->key_hash = self::hash($token);
        $key->save();

        return [$key, $token];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
