<?php

declare(strict_types=1);

namespace App\Services\CardPreview;

/** ホスト名の名前解決（テストで差し替えられるよう分けている） */
class HostResolver
{
    /** @return list<string> IPv4 / IPv6 アドレス */
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];

        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            // dns_get_record が使えない環境向け（IPv4 のみ）
            $addresses = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($addresses));
    }
}
