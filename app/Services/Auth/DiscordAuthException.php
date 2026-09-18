<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

/** Discord ログインの失敗。メッセージはそのまま利用者に表示する */
final class DiscordAuthException extends RuntimeException {}
