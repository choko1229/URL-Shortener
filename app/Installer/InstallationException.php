<?php

declare(strict_types=1);

namespace App\Installer;

use RuntimeException;

/** セットアップの失敗。メッセージはそのまま利用者に表示する */
final class InstallationException extends RuntimeException {}
