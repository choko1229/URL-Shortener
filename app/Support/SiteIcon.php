<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IconName;
use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * サービスアイコン（ヘッダーのロゴマークとファビコン）。
 * 内蔵のアイコンから選ぶか、画像をアップロードする。ファビコンにも同じものを使う。
 */
final class SiteIcon
{
    use ReadsSiteSettings;

    /** 内蔵アイコン（値は IconName） */
    public const BUILT_IN = [
        'logo' => '矢印（既定）',
        'link' => 'リンク',
        'share' => '共有',
        'qr-code' => 'QRコード',
        'bar-chart' => 'グラフ',
        'shield' => 'たて',
    ];

    public const UPLOADED = 'upload';

    public const MAX_KILOBYTES = 512;

    /** SVG は中にスクリプトを書けるため受け付けない */
    public const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    /** @var array<string, string> */
    private const MIME_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    public function __construct(private readonly string $directory) {}

    public function isUploaded(): bool
    {
        return $this->stored(AppSetting::SITE_ICON) === self::UPLOADED && $this->path() !== null;
    }

    /** 内蔵アイコンを選んでいる場合の種類 */
    public function builtIn(): IconName
    {
        $value = (string) $this->stored(AppSetting::SITE_ICON);

        return array_key_exists($value, self::BUILT_IN) ? IconName::from($value) : IconName::Logo;
    }

    /** サイト設定の画面で「選択中」を示すための値 */
    public function selected(): string
    {
        return $this->isUploaded() ? self::UPLOADED : $this->builtIn()->value;
    }

    /** アップロードされた画像の場所（無ければ null） */
    public function path(): ?string
    {
        foreach (self::ALLOWED_EXTENSIONS as $extension) {
            $path = $this->directory.DIRECTORY_SEPARATOR.'icon.'.$extension;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function mimeType(): ?string
    {
        $path = $this->path();

        return $path === null ? null : (self::MIME_TYPES[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream');
    }

    /** 画像を差し替えたときにブラウザのキャッシュを更新するための値 */
    public function version(): string
    {
        $path = $this->path();

        return $path === null ? '0' : substr(hash('xxh3', (string) filemtime($path)), 0, 8);
    }

    /** 内蔵アイコンをそのままファビコンにする（画像ファイルを持たずに済む） */
    public function faviconDataUri(string $color): string
    {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
            .'<rect width="24" height="24" rx="6" fill="%s"/>'
            .'<g transform="translate(4 4) scale(0.6667)" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">%s</g>'
            .'</svg>',
            $color,
            $this->builtIn()->svgContent(),
        );

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }

    public function useBuiltIn(string $name): void
    {
        $this->removeUploaded();
        AppSetting::store(AppSetting::SITE_ICON, array_key_exists($name, self::BUILT_IN) ? $name : IconName::Logo->value);
        $this->forgetStoredSettings();
    }

    /** @throws RuntimeException 保存できない場合 */
    public function store(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('対応していない画像の種類です。');
        }

        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("{$this->directory} を作成できません。");
        }

        $this->removeUploaded();
        $file->move($this->directory, 'icon.'.$extension);

        AppSetting::store(AppSetting::SITE_ICON, self::UPLOADED);
        $this->forgetStoredSettings();
    }

    private function removeUploaded(): void
    {
        $path = $this->path();

        if ($path !== null) {
            @unlink($path);
        }
    }
}
