<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $discord_id
 * @property string $username
 * @property string|null $global_name
 * @property string|null $avatar_hash
 * @property UserRole $role
 * @property CarbonImmutable|null $last_login_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, SoftDeletes;

    /** Discord のアバターハッシュ形式（アニメーションは a_ 接頭辞） */
    private const AVATAR_HASH_PATTERN = '/\A(?:a_)?[0-9a-f]{32}\z/';

    /** @var list<string> */
    protected $fillable = [
        'discord_id',
        'username',
        'global_name',
        'avatar_hash',
        'last_login_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'remember_token',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'role' => 'member',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'last_login_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ShortUrl, $this> */
    public function shortUrls(): HasMany
    {
        return $this->hasMany(ShortUrl::class);
    }

    /** @return HasMany<ApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** 管理者が一人でもいるか（いなければ、セットアップ直後で最初のログインを待っている状態） */
    public static function adminExists(): bool
    {
        return static::query()->where('role', UserRole::Admin->value)->exists();
    }

    public function displayName(): string
    {
        return $this->global_name !== null && $this->global_name !== '' ? $this->global_name : $this->username;
    }

    /** 形式を検証したうえで Discord CDN の URL を組み立てる。不正・未設定なら null */
    public function avatarUrl(int $size = 64): ?string
    {
        if ($this->avatar_hash === null || preg_match(self::AVATAR_HASH_PATTERN, $this->avatar_hash) !== 1) {
            return null;
        }

        if (preg_match('/\A\d{1,32}\z/', $this->discord_id) !== 1) {
            return null;
        }

        return sprintf('https://cdn.discordapp.com/avatars/%s/%s.png?size=%d', $this->discord_id, $this->avatar_hash, $size);
    }
}
