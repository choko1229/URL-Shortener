<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property bool $is_encrypted
 */
class AppSetting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'key',
    ];

    /** @var list<string> */
    protected $hidden = [
        'value',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * 設定値を取り出す。未登録なら null。
     * 復号・JSON デコードに失敗した場合はログに残して null を返す（値そのものはログに出さない）。
     *
     * @throws QueryException DB に接続できない場合
     */
    public static function valueFor(string $key): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->decodedValue();
    }

    public function decodedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        try {
            $json = $this->is_encrypted ? Crypt::decryptString($this->value) : $this->value;

            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $e) {
            Log::error('app_settings の値を復元できませんでした。', [
                'key' => $this->key,
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /** 値を JSON エンコード（必要なら暗号化）して設定する */
    public function assignValue(mixed $value, bool $encrypt = false): void
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->value = $encrypt ? Crypt::encryptString($json) : $json;
        $this->is_encrypted = $encrypt;
    }
}
