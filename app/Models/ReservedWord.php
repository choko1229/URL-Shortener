<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservedWordCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $word
 * @property ReservedWordCategory $category
 * @property int|null $created_by_user_id
 */
class ReservedWord extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'word',
        'category',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => ReservedWordCategory::class,
            'created_by_user_id' => 'integer',
        ];
    }

    /** 大文字小文字違い（Admin / ADMIN 等）での回避を防ぐため小文字で保存する */
    protected function word(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
