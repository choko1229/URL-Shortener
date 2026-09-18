<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** 自サービス（chok.ooo 各サブドメイン）の URL を短縮するとリダイレクトが循環するため拒否する */
final class NotOwnDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = is_string($value) ? parse_url($value, PHP_URL_HOST) : null;
        $ownHosts = array_map('strtolower', array_filter((array) config('shortener.domains'), 'is_string'));

        if (is_string($host) && in_array(strtolower($host), $ownHosts, true)) {
            $fail('chok.ooo 自身の URL は短縮できません。');
        }
    }
}
