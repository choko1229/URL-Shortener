<?php

declare(strict_types=1);

namespace App\Services\Account;

use RuntimeException;

/** 権限変更・退会ができない理由。メッセージはそのまま利用者に表示する */
final class AccountException extends RuntimeException {}
