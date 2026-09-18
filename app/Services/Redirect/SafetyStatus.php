<?php

declare(strict_types=1);

namespace App\Services\Redirect;

enum SafetyStatus: string
{
    case Safe = 'safe';
    // 危険と判定された（転送を中止する）
    case Unsafe = 'unsafe';
    // 確認できなかった（警告を出して利用者に任せる）
    case Unknown = 'unknown';
}
