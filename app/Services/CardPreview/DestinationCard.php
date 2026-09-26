<?php

declare(strict_types=1);

namespace App\Services\CardPreview;

/** 転送先ページのカード（OGP / Twitter カード）の内容 */
final readonly class DestinationCard
{
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $imageUrl,
        // twitter:card が summary_large_image のとき true（X では大きな画像のカードになる）
        public bool $largeImage,
    ) {}

    /** @return array{title: string|null, description: string|null, image: string|null, large: bool} */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->imageUrl,
            'large' => $this->largeImage,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            title: is_string($data['title'] ?? null) ? $data['title'] : null,
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            imageUrl: is_string($data['image'] ?? null) ? $data['image'] : null,
            largeImage: ($data['large'] ?? false) === true,
        );
    }
}
