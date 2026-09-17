<?php

declare(strict_types=1);

namespace App\ViewModels;

final readonly class HomePageData
{
    public function __construct(
        public ViewerData $viewer,
        public ShortUrlFormData $form,
        public ?IssuedLinkData $issuedLink,
        public string $displayTimezone,
    ) {}
}
