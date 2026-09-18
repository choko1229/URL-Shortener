<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 短縮URLの QR コード（requirements.md 6 章）。SVG の data URI を返す */
final class QrCodeGenerator
{
    private const SIZE = 240;

    private const MARGIN = 8;

    /** 生成に失敗した場合は null（発行自体は成功させる） */
    public function dataUri(string $url): ?string
    {
        try {
            return (new Builder(
                writer: new SvgWriter,
                data: $url,
                size: self::SIZE,
                margin: self::MARGIN,
            ))->build()->getDataUri();
        } catch (Throwable $e) {
            Log::warning('QR コードを生成できませんでした。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
