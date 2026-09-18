<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SlugType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $slug_normalized
 * @property SlugType $slug_type
 * @property int|null $short_url_id
 * @property CarbonImmutable $retired_at
 */
class RetiredSlug extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'slug_type',
        'short_url_id',
        'retired_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'slug_type' => SlugType::class,
            'short_url_id' => 'integer',
            'retired_at' => 'immutable_datetime',
        ];
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): array => [
                'slug' => $value,
                'slug_normalized' => mb_strtolower($value),
            ],
        );
    }
}
