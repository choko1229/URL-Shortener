<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * お問い合わせ（フォームから送られた内容）
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $reply_to
 * @property string $message
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable|null $created_at
 *
 * @use HasFactory<InquiryFactory>
 */
class Inquiry extends Model
{
    /** @use HasFactory<InquiryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'reply_to',
        'message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'handled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }

    /** 送信者の表示名（未入力なら「名前なし」） */
    public function senderLabel(): string
    {
        return match (true) {
            $this->name !== null && $this->name !== '' => $this->name,
            $this->user !== null => $this->user->displayName(),
            default => '名前なし',
        };
    }
}
