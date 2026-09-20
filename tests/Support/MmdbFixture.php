<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;

/**
 * テスト用の最小限の MaxMind DB（MMDB）ファイルを作る。
 * 1 つの IPv4 ネットワークだけに country.iso_code を持つ、IPv4 専用・レコード長 24 ビットのデータベース。
 *
 * @see https://maxmind.github.io/MaxMind-DB/
 */
final class MmdbFixture
{
    private const TYPE_UTF8_STRING = 2;

    private const TYPE_UINT16 = 5;

    private const TYPE_UINT32 = 6;

    private const TYPE_MAP = 7;

    private const TYPE_UINT64 = 9;

    private const TYPE_ARRAY = 11;

    public static function build(string $cidr, string $isoCode, string $databaseType = 'DBIP-Country-Lite'): string
    {
        [$network, $prefixLength] = explode('/', $cidr) + [1 => '32'];
        $packed = inet_pton($network);
        $prefixLength = (int) $prefixLength;

        if ($packed === false || strlen($packed) !== 4 || $prefixLength < 1 || $prefixLength > 32) {
            throw new InvalidArgumentException("IPv4 の CIDR を指定してください: {$cidr}");
        }

        $bits = str_pad(decbin((int) unpack('N', $packed)[1]), 32, '0', STR_PAD_LEFT);
        $nodeCount = $prefixLength;
        // データ部の先頭（オフセット 0）を指すレコード値
        $dataPointer = $nodeCount + 16;

        $tree = '';
        for ($i = 0; $i < $prefixLength; $i++) {
            $next = $i === $prefixLength - 1 ? $dataPointer : $i + 1;
            [$left, $right] = $bits[$i] === '0' ? [$next, $nodeCount] : [$nodeCount, $next];
            $tree .= substr(pack('N', $left), 1).substr(pack('N', $right), 1);
        }

        $data = self::map(['country' => self::map(['iso_code' => self::string($isoCode)])]);

        $metadata = self::map([
            'binary_format_major_version' => self::uint(self::TYPE_UINT16, 2),
            'binary_format_minor_version' => self::uint(self::TYPE_UINT16, 0),
            'build_epoch' => self::uint(self::TYPE_UINT64, 1_700_000_000),
            'database_type' => self::string($databaseType),
            'description' => self::map(['en' => self::string('URL-Shortener test database')]),
            'ip_version' => self::uint(self::TYPE_UINT16, 4),
            'languages' => self::control(self::TYPE_ARRAY, 1).self::string('en'),
            'node_count' => self::uint(self::TYPE_UINT32, $nodeCount),
            'record_size' => self::uint(self::TYPE_UINT16, 24),
        ]);

        return $tree.str_repeat("\0", 16).$data."\xAB\xCD\xEFMaxMind.com".$metadata;
    }

    /** @param  array<string, string>  $entries  キー => エンコード済みの値 */
    private static function map(array $entries): string
    {
        $encoded = self::control(self::TYPE_MAP, count($entries));

        foreach ($entries as $key => $value) {
            $encoded .= self::string($key).$value;
        }

        return $encoded;
    }

    private static function string(string $value): string
    {
        return self::control(self::TYPE_UTF8_STRING, strlen($value)).$value;
    }

    private static function uint(int $type, int $value): string
    {
        $bytes = $value === 0 ? '' : ltrim(pack('J', $value), "\0");

        return self::control($type, strlen($bytes)).$bytes;
    }

    private static function control(int $type, int $size): string
    {
        if ($size >= 29) {
            throw new InvalidArgumentException('テスト用のため、29 未満の大きさのみ対応しています。');
        }

        // 拡張型（8 以上）は、型ビット 0 の制御バイトの次に「型 - 7」を置く
        return $type <= 7 ? chr(($type << 5) | $size) : chr($size).chr($type - 7);
    }
}
