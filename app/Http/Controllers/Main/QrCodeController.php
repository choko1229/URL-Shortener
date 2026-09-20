<?php

declare(strict_types=1);

namespace App\Http\Controllers\Main;

use App\Enums\QrFormat;
use App\Http\Controllers\Controller;
use App\Services\ShortUrl\QrCodeGenerator;
use App\Services\ShortUrl\ShortUrlResolver;
use App\Support\ShortUrlBuilder;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * 短縮URLの QR コード（requirements.md 6 章）。SVG と PNG を選んでダウンロードできる。
 * 中身は短縮URLそのもの（公開情報）のため、発行者以外でも取得できる。
 */
final class QrCodeController extends Controller
{
    public function __invoke(string $code, string $format, ShortUrlResolver $resolver, QrCodeGenerator $qrCodes, ShortUrlBuilder $urls): Response
    {
        $type = QrFormat::tryFrom($format);
        abort_if($type === null, Response::HTTP_NOT_FOUND);

        $link = $resolver->find($code);
        abort_if($link === null || $link->trashed(), Response::HTTP_NOT_FOUND);

        $image = $qrCodes->image($urls->url($link->slug), $type);
        abort_if($image === null, Response::HTTP_NOT_FOUND);

        return response($image, Response::HTTP_OK, [
            'Content-Type' => $type->contentType(),
            // スラッグは英数字・ハイフン・アンダースコアのみのため、そのままファイル名に使える
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                "{$link->slug}-qr.{$type->value}",
            ),
            // 画像として扱う前提。直接開かれてもスクリプト等を実行させない
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
