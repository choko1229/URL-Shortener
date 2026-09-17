<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $short_url_id
 * @property string|null $referrer_host
 * @property string|null $country_code
 * @property DeviceType $device_type
 * @property CarbonImmutable $clicked_at
 */
class ShortUrlClick extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'referrer_host',
        'country_code',
        'device_type',
        'clicked_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'short_url_id' => 'integer',
            'device_type' => DeviceType::class,
            'clicked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ShortUrl, $this> */
    public function shortUrl(): BelongsTo
    {
        return $this->belongsTo(ShortUrl::class);
    }
}
