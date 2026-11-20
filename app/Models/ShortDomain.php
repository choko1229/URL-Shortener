<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShortDomainMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * 短縮URLに使える追加のドメイン（管理画面で追加する）
 *
 * @property int $id
 * @property string $host
 * @property ShortDomainMode $mode
 * @property CarbonImmutable|null $created_at
 */
class ShortDomain extends Model
{
    /** @var list<string> */
    protected $fillable = ['host', 'mode'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mode' => ShortDomainMode::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function allowsPage(): bool
    {
        return $this->mode === ShortDomainMode::Page;
    }
}
