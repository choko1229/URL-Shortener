<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * メインドメインの固定ページの URL（/terms・/privacy・/contact・/delete）。
 * 管理者が「contact」などを短縮URLとして使えるよう、ページ側の URL を変えられるようにする。
 * ルートの登録時（routes/web.php）に読むため、データベースを読めないときは既定の URL を使う。
 */
final class SitePaths
{
    /** @var array<string, array{default: string, label: string}> ページ（ルート名の末尾）=> 既定の URL と名前 */
    public const PAGES = [
        'terms' => ['default' => 'terms', 'label' => '利用規約'],
        'privacy' => ['default' => 'privacy', 'label' => 'プライバシーポリシー'],
        'contact' => ['default' => 'contact', 'label' => 'お問い合わせ'],
        'delete' => ['default' => 'delete', 'label' => '短縮URLの削除（削除用トークン）'],
    ];

    /** 短縮コードと同じ文字種で、1 階層だけ */
    public const PATTERN = '/\A[A-Za-z0-9_-]{1,20}\z/';

    /** @var array<string, string>|null */
    private ?array $resolved = null;

    public function path(string $page): string
    {
        return $this->all()[$page] ?? self::PAGES[$page]['default'];
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        try {
            $stored = AppSetting::valueFor(AppSetting::SITE_PATHS);
        } catch (Throwable $e) {
            // ルートの登録中に呼ばれるため、セットアップ前などで何が起きても既定の URL で動かす
            Log::debug('固定ページの URL を読み込めませんでした。', ['exception' => $e::class]);
            $stored = null;
        }

        $paths = [];
        foreach (self::PAGES as $page => $definition) {
            $value = is_array($stored) ? ($stored[$page] ?? null) : null;
            $paths[$page] = is_string($value) && preg_match(self::PATTERN, $value) === 1 ? $value : $definition['default'];
        }

        return $this->resolved = $paths;
    }

    /** @param  array<string, string>  $paths */
    public function save(array $paths): void
    {
        $values = [];
        foreach (array_keys(self::PAGES) as $page) {
            $values[$page] = $paths[$page] ?? self::PAGES[$page]['default'];
        }

        AppSetting::store(AppSetting::SITE_PATHS, $values);
        $this->forget();
    }

    public function forget(): void
    {
        $this->resolved = null;
    }
}
