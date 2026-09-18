<?php

declare(strict_types=1);

namespace App\Services\Redirect;

use App\Enums\DeviceType;

/** User-Agent からデバイス種別を大まかに判定する（requirements.md 2-6） */
final class DeviceDetector
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|headless|curl|wget|python-requests|go-http-client|java\//i';

    private const TABLET_PATTERN = '/ipad|tablet|kindle|silk|playbook|android(?!.*mobile)/i';

    private const MOBILE_PATTERN = '/mobi|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini/i';

    public function detect(?string $userAgent): DeviceType
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return DeviceType::Unknown;
        }

        return match (true) {
            preg_match(self::BOT_PATTERN, $userAgent) === 1 => DeviceType::Bot,
            preg_match(self::TABLET_PATTERN, $userAgent) === 1 => DeviceType::Tablet,
            preg_match(self::MOBILE_PATTERN, $userAgent) === 1 => DeviceType::Mobile,
            default => DeviceType::Desktop,
        };
    }
}
