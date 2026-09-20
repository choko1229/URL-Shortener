<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SiteIcon;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * アップロードされたサービスアイコンを配信する。
 * 公開フォルダに置かず（symlink が使えないサーバーがあるため）、ここから読み出す。
 */
final class SiteIconController extends Controller
{
    private const CACHE_SECONDS = 86400;

    public function __invoke(SiteIcon $icon): BinaryFileResponse
    {
        $path = $icon->isUploaded() ? $icon->path() : null;

        abort_if($path === null, Response::HTTP_NOT_FOUND);

        return response()
            ->file($path, [
                'Content-Type' => (string) $icon->mimeType(),
                'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
                // 画像として以外に解釈されないようにする
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ]);
    }
}
