<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * 業務ルール値の読み出し口。
 * app_settings テーブルの値を優先し、無い・読めない場合は config/shortener.php の初期値を使う。
 */
final class ShortenerSettings
{
    public const ADMIN_CUSTOM_SLUG_MIN_LENGTH = 1;

    /** short_urls.slug の列の長さ。設定にかかわらず、これより長いスラッグは保存できない */
    public const SLUG_COLUMN_LENGTH = 20;

    /** @var array<string, int> リクエスト内キャッシュ */
    private array $resolved = [];

    public function __construct(private readonly Config $config) {}

    public function memberMonthlyLimit(): int
    {
        return $this->int('member_monthly_limit');
    }

    public function guestMonthlyLimit(): int
    {
        return $this->int('guest_monthly_limit');
    }

    public function guestMaxExpiryDays(): int
    {
        return $this->int('guest_max_expiry_days');
    }

    public function expiryWarningDays(): int
    {
        return $this->int('expiry_warning_days');
    }

    public function customSlugMinLength(): int
    {
        return $this->int('custom_slug_min_length');
    }

    public function customSlugMaxLength(): int
    {
        return $this->int('custom_slug_max_length');
    }

    /** 管理者は 1 文字のカスタムスラッグも使える（短いほど貴重なため、一般ユーザーには開放しない） */
    public function customSlugMinLengthFor(?User $user): int
    {
        return ($user?->isAdmin() ?? false) ? self::ADMIN_CUSTOM_SLUG_MIN_LENGTH : $this->customSlugMinLength();
    }

    public function dashboardLinksPerPage(): int
    {
        return $this->int('dashboard_links_per_page');
    }

    public function randomCodeLength(): int
    {
        return $this->int('random_code_length');
    }

    public function memberRateLimitPerMinute(): int
    {
        return $this->int('member_rate_limit_per_minute');
    }

    public function guestRateLimitIntervalMinutes(): int
    {
        return $this->int('guest_rate_limit_interval_minutes');
    }

    public function passwordMaxAttempts(): int
    {
        return $this->int('password_max_attempts');
    }

    public function passwordLockoutMinutes(): int
    {
        return $this->int('password_lockout_minutes');
    }

    public function safeBrowsingCacheDays(): int
    {
        return $this->int('safe_browsing_cache_days');
    }

    public function redirectTicketTtlMinutes(): int
    {
        return $this->int('redirect_ticket_ttl_minutes');
    }

    public function recaptchaMinimumScore(): float
    {
        return min(100, $this->int('recaptcha_min_score_percent')) / 100;
    }

    public function displayTimezone(): string
    {
        return (string) $this->config->get('shortener.display_timezone', 'Asia/Tokyo');
    }

    private function int(string $key): int
    {
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        $default = $this->config->get("shortener.defaults.{$key}");
        if (! is_int($default)) {
            throw new InvalidArgumentException("shortener.defaults.{$key} が整数で定義されていません。");
        }

        return $this->resolved[$key] = $this->override($key) ?? $default;
    }

    private function override(string $key): ?int
    {
        try {
            $value = AppSetting::valueFor($key);
        } catch (QueryException $e) {
            Log::warning('app_settings を読み込めないため初期値を使用します。', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            Log::warning('app_settings の値が不正なため初期値を使用します。', ['key' => $key]);

            return null;
        }

        return $value;
    }
}
