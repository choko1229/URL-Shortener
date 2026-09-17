<?php

declare(strict_types=1);

namespace App\Installer;

final readonly class RequirementResult
{
    /**
     * @param  list<string>  $links  利用者が手動で確認するための URL
     */
    public function __construct(
        public string $label,
        public RequirementStatus $status,
        public ?string $detail = null,
        public array $links = [],
    ) {}
}
