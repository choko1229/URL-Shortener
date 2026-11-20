<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ShortDomain;
use App\Support\ShortDomains;
use App\Support\SiteIdentity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** 自サービス（各サブドメイン・短縮URL用に追加したドメイン）の URL を短縮するとリダイレクトが循環するため拒否する */
final class NotOwnDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = is_string($value) ? parse_url($value, PHP_URL_HOST) : null;

        if (! is_string($host)) {
            return;
        }

        $ownHosts = array_map('strtolower', array_filter((array) config('shortener.domains'), 'is_string'));
        $extraHosts = array_map(static fn (ShortDomain $domain): string => $domain->host, app(ShortDomains::class)->extra());

        if (in_array(strtolower($host), [...$ownHosts, ...$extraHosts], true)) {
            $fail(app(SiteIdentity::class)->name().' 自身の URL は短縮できません。');
        }
    }
}
