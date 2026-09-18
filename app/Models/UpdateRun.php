<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UpdateRunStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $from_version
 * @property string $to_version
 * @property string $strategy
 * @property UpdateRunStatus $status
 * @property string|null $message
 * @property string|null $backup_path
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 */
class UpdateRun extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'from_version',
        'to_version',
        'strategy',
        'status',
        'message',
        'backup_path',
        'started_at',
        'finished_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => UpdateRunStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
