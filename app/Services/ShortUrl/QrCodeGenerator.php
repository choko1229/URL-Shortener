<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\QrFormat;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 短縮URLの QR コード（requirements.md 6 章）。画面表示は SVG、ダウンロードは SVG / PNG を選べる */
final class QrCodeGenerator
{
    private const SIZE = 240;

    private const MARGIN = 8;

    /** 画面に埋め込む SVG の data URI。生成に失敗した場合は null */
    public function dataUri(string $url): ?string
    {
        return $this->build($url, new SvgWriter)?->getDataUri();
    }

    /** ダウンロード・表示用の画像データ。生成に失敗した場合は null */
    public function image(string $url, QrFormat $format): ?string
    {
        if ($format === QrFormat::Png && ! self::supportsPng()) {
            return null;
        }

        return $this->build($url, $format === QrFormat::Png ? new PngWriter : new SvgWriter)?->getString();
    }

    /** PNG の生成には GD 拡張が必要。使えないサーバーでは SVG のみ提供する */
    public static function supportsPng(): bool
    {
        return extension_loaded('gd');
    }

    private function build(string $url, WriterInterface $writer): ?ResultInterface
    {
        try {
            return (new Builder(
                writer: $writer,
                data: $url,
                size: self::SIZE,
                margin: self::MARGIN,
            ))->build();
        } catch (Throwable $e) {
            Log::warning('QR コードを生成できませんでした。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
