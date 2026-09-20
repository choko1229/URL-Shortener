<?php

declare(strict_types=1);

namespace App\Services\GeoIp;

use RuntimeException;

/** 指定した月のデータベースがまだ公開されていない（月初の公開前など） */
final class ReleaseNotPublished extends RuntimeException {}
