<?php

declare(strict_types=1);

namespace App\Services\Update;

use RuntimeException;

/** 自動アップデートの失敗。メッセージは管理画面・通知にそのまま表示する */
final class UpdateException extends RuntimeException {}
