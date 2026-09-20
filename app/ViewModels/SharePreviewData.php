<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\PreviewMode;
use App\Models\ShortUrl;
use App\Support\SiteIdentity;

/** SNS に貼ったときのカード（OGP）に出す内容 */
final readonly class SharePreviewData
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $imageUrl,
        public string $url,
    ) {}

    /** 「内容を指定する」以外は、サービス名だけの控えめなカードにする */
    public static function forLink(ShortUrl $link, SiteIdentity $site, string $shortUrl): self
    {
        $custom = $link->previewMode() === PreviewMode::Custom;

        return new self(
            title: $custom && $link->preview_title !== null ? $link->preview_title : $site->name(),
            description: $custom && $link->preview_description !== null
                ? $link->preview_description
                : $site->name().' で短縮されたリンクです。',
            imageUrl: $custom ? $link->preview_image_url : null,
            url: $shortUrl,
        );
    }
}
