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
    public const DISCORD_CLIENT_ID = 'discord.client_id';

    // APP_KEY で暗号化して保存する（requirements.md 7-2, 10）
    public const DISCORD_CLIENT_SECRET = 'discord.client_secret';

    // 暗号化して保存する
    public const SAFE_BROWSING_API_KEY = 'safe_browsing.api_key';

    // サイトキーはページに埋め込む公開値のため平文で保存する
    public const RECAPTCHA_SITE_KEY = 'recaptcha.site_key';

    // reCAPTCHA のキーがある Google Cloud のプロジェクト ID（評価の作成先）
    public const RECAPTCHA_PROJECT_ID = 'recaptcha.project_id';

    // 評価の作成に使う Google Cloud の API キー。暗号化して保存する
    public const RECAPTCHA_API_KEY = 'recaptcha.api_key';

    // 自動アップデート（requirements.md 7 章）
    public const UPDATE_ENABLED = 'update.enabled';

    public const UPDATE_REPOSITORY = 'update.repository';

    // GitHub のトークンは APP_KEY で暗号化して保存する（requirements.md 7-2）
    public const UPDATE_GITHUB_TOKEN = 'update.github_token';

    // 管理者への通知先。URL 自体が投稿権限を持つため暗号化して保存する
    public const DISCORD_WEBHOOK_URL = 'notifications.discord_webhook_url';

    // お問い合わせページに公開する Discord の連絡先（ユーザー名や招待リンク）
    public const CONTACT_DISCORD = 'contact.discord';

    // サイトの表示（管理画面「サイト設定」で変更する）
    public const SITE_NAME = 'site.name';

    public const SITE_TAGLINE = 'site.tagline';

    public const SITE_OPERATOR = 'site.operator';

    // 見た目（管理画面「サイト設定」で変更する）
    public const SITE_THEME_COLOR = 'site.theme_color';

    public const SITE_COLOR_SCHEME = 'site.color_scheme';

    public const SITE_FONT = 'site.font';

    public const SITE_ICON = 'site.icon';

    // 固定ページの URL（ページ => パス）
    public const SITE_PATHS = 'site.paths';

    // 限定モード（管理画面「サイト設定」で変更する）
    public const ACCESS_MODE = 'access.mode';

    public const ACCESS_OUTSIDER_ACTION = 'access.outsider_action';

    public const ACCESS_REDIRECT_URL = 'access.redirect_url';

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

    /**
     * 設定値を保存する（既存のキーは上書き）
     *
     * @throws QueryException DB に接続できない場合
     */
    public static function store(string $key, mixed $value, bool $encrypt = false): self
    {
        $setting = static::query()->firstOrNew(['key' => $key]);
        $setting->assignValue($value, $encrypt);
        $setting->save();

        return $setting;
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
