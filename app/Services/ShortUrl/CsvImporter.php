<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\ExpiryOption;
use App\Enums\PreviewMode;
use App\Models\User;
use App\Support\ShortenerSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CSV からまとめて短縮URLを発行する（管理者のみ）。
 * 1 行ずつ検証し、問題のある行だけを飛ばして理由を返す。
 */
final class CsvImporter
{
    /** 見出し行に書ける列（url 以外は任意） */
    public const COLUMNS = [
        'url',
        'slug',
        'expires_at',
        'password',
        'preview_mode',
        'preview_title',
        'preview_description',
        'preview_image_url',
    ];

    public const MAX_ROWS = 500;

    private const MAX_EXECUTION_SECONDS = 300;

    /** Excel で保存した CSV は UTF-8 とは限らないため、この順で判定する */
    private const ENCODINGS = 'UTF-8, SJIS-win, EUC-JP';

    public function __construct(
        private readonly ShortUrlIssuer $issuer,
        private readonly ShortenerSettings $settings,
        private readonly ValidationFactory $validator,
    ) {}

    /** 取り込み用のひな形（Excel でも文字化けしないよう BOM を付ける） */
    public static function template(): string
    {
        $rows = [
            self::COLUMNS,
            ['https://example.com/very/long/path', 'spring-sale', '2026-12-31 23:59', '', 'destination', '', '', ''],
            ['https://example.com/members/report.pdf', '', '', 'secret123', 'service', '', '', ''],
            ['https://example.com/event', 'event2026', '', '', 'custom', '秋のイベント', '10月1日に開催します', 'https://example.com/ogp.png'],
        ];

        $csv = "\u{FEFF}";
        foreach ($rows as $row) {
            $csv .= self::line($row);
        }

        return $csv;
    }

    public function import(string $path, User $admin, string $clientIp): CsvImportResult
    {
        @set_time_limit(self::MAX_EXECUTION_SECONDS);

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return CsvImportResult::failed(0, 'ファイルを読み込めませんでした。');
        }

        $rows = self::parse($contents);

        if ($rows === []) {
            return CsvImportResult::failed(1, 'CSV が空です。1行目に見出し（url など）を入れてください。');
        }

        $header = self::header(array_shift($rows));

        if (! in_array('url', $header, true)) {
            return CsvImportResult::failed(1, '1行目の見出しに url がありません。テンプレートをダウンロードして使ってください。');
        }

        if (count($rows) > self::MAX_ROWS) {
            return CsvImportResult::failed(1, '一度に取り込めるのは'.self::MAX_ROWS.'行までです。分割してください。');
        }

        return $this->importRows($header, $rows, $admin, $clientIp);
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    private function importRows(array $header, array $rows, User $admin, string $clientIp): CsvImportResult
    {
        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            // 見出しを 1 行目として数える
            $line = $index + 2;
            $values = self::combine($header, $row);

            if ($values === []) {
                continue;
            }

            $error = $this->importRow($values, $admin, $clientIp);

            $error === null ? $imported++ : $errors[$line] = $error;
        }

        Log::notice('CSV から短縮URLを取り込みました。', ['user_id' => $admin->id, 'imported' => $imported, 'skipped' => count($errors)]);

        return new CsvImportResult($imported, $errors);
    }

    /** @param  array<string, string>  $values */
    private function importRow(array $values, User $admin, string $clientIp): ?string
    {
        $validator = $this->validator->make(
            $values,
            LinkRules::basic($this->settings) + LinkRules::preview(),
            LinkRules::messages(),
        );

        if ($validator->fails()) {
            return implode(' / ', $validator->errors()->all());
        }

        try {
            // 管理者による一括登録のため、月間上限・レート制限は適用しない
            $this->issuer->issue($this->draft($validator->validated()), $admin, $clientIp, enforceLimits: false);
        } catch (IssuanceException $e) {
            return $e->getMessage();
        } catch (Throwable $e) {
            Log::error('CSV インポートで発行に失敗しました。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            return '発行できませんでした。';
        }

        return null;
    }

    /** @param  array<string, mixed>  $values */
    private function draft(array $values): ShortUrlDraft
    {
        $expiresAt = self::text($values, 'expires_at');
        // タイムゾーンの指定が無い日時は表示タイムゾーン（日本時間）として扱う
        $expires = $expiresAt === null ? null : CarbonImmutable::parse($expiresAt, $this->settings->displayTimezone());

        return new ShortUrlDraft(
            originalUrl: (string) self::text($values, 'url'),
            customSlug: self::text($values, 'slug'),
            expiry: $expires === null ? ExpiryOption::Never : ExpiryOption::Custom,
            expiresAtLocal: null,
            password: self::text($values, 'password'),
            explicitExpiresAt: $expires,
            previewMode: PreviewMode::tryFrom(self::text($values, 'preview_mode') ?? '') ?? PreviewMode::Destination,
            previewTitle: self::text($values, 'preview_title'),
            previewDescription: self::text($values, 'preview_description'),
            previewImageUrl: self::text($values, 'preview_image_url'),
        );
    }

    /** @param  array<string, mixed>  $values */
    private static function text(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return list<list<string>> */
    private static function parse(string $contents): array
    {
        $encoding = mb_detect_encoding($contents, self::ENCODINGS, true);
        $contents = mb_convert_encoding($contents, 'UTF-8', $encoding !== false ? $encoding : 'UTF-8');
        // Excel が付ける BOM を取り除く
        $contents = preg_replace('/\A\x{FEFF}/u', '', $contents) ?? $contents;

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }

        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rows[] = array_map(static fn (?string $value): string => (string) $value, $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string>  $row
     * @return list<string>
     */
    private static function header(array $row): array
    {
        return array_map(static fn (string $value): string => strtolower(trim($value)), $row);
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $row
     * @return array<string, string> 空行の場合は空配列
     */
    private static function combine(array $header, array $row): array
    {
        $values = [];

        foreach ($header as $index => $column) {
            if (in_array($column, self::COLUMNS, true)) {
                $values[$column] = trim($row[$index] ?? '');
            }
        }

        return array_filter($values, static fn (string $value): bool => $value !== '') === [] ? [] : $values;
    }

    /** @param  list<string>  $row */
    private static function line(array $row): string
    {
        $escaped = array_map(
            static fn (string $value): string => str_contains($value, ',') || str_contains($value, '"')
                ? '"'.str_replace('"', '""', $value).'"'
                : $value,
            $row,
        );

        return implode(',', $escaped)."\r\n";
    }
}
