<?php

declare(strict_types=1);

namespace App\Services\Redirect;

final readonly class SafetyVerdict
{
    private const THREAT_LABELS = [
        'MALWARE' => 'マルウェア',
        'SOCIAL_ENGINEERING' => 'フィッシング・詐欺',
        'UNWANTED_SOFTWARE' => '望ましくないソフトウェア',
        'POTENTIALLY_HARMFUL_APPLICATION' => '有害なアプリ',
    ];

    /**
     * @param  list<string>  $threats  Safe Browsing の threatType
     */
    private function __construct(
        public SafetyStatus $status,
        public array $threats,
        public ?string $reason,
    ) {}

    public static function safe(): self
    {
        return new self(SafetyStatus::Safe, [], null);
    }

    /** @param  list<string>  $threats */
    public static function unsafe(array $threats): self
    {
        return new self(SafetyStatus::Unsafe, $threats, null);
    }

    public static function unknown(string $reason): self
    {
        return new self(SafetyStatus::Unknown, [], $reason);
    }

    /** @return list<string> */
    public function threatLabels(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $threat): string => self::THREAT_LABELS[$threat] ?? 'その他の脅威',
            $this->threats,
        )));
    }

    /** @return array{status: string, threats: list<string>} */
    public function toCache(): array
    {
        return ['status' => $this->status->value, 'threats' => $this->threats];
    }

    /** @param  mixed  $cached  toCache() の値 */
    public static function fromCache(mixed $cached): ?self
    {
        if (! is_array($cached) || ! is_string($cached['status'] ?? null) || ! is_array($cached['threats'] ?? null)) {
            return null;
        }

        return match (SafetyStatus::tryFrom($cached['status'])) {
            SafetyStatus::Safe => self::safe(),
            SafetyStatus::Unsafe => self::unsafe(array_values(array_filter($cached['threats'], 'is_string'))),
            default => null,
        };
    }
}
