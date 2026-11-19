<?php

declare(strict_types=1);

namespace App\Services\ShortUrl\Transfer;

use App\Enums\PreviewMode;
use App\Enums\SlugType;
use App\Models\RetiredSlug;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\ShortUrl\CsvImporter;
use App\Services\ShortUrl\CsvImportError;
use App\Services\ShortUrl\IssuanceLimiter;
use App\Services\ShortUrl\LinkRules;
use App\Services\ShortUrl\SlugAvailability;
use App\Services\ShortUrl\SlugGenerator;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * 発行履歴の CSV エクスポート・インポート（別のサーバーへの移行用）。
 *
 * - 短縮コード（slug）をそのまま持っていき、移行先でも同じ短縮URLで開けるようにする
 * - パスワードは平文を持っていないため、ハッシュ値（password_hash）を書き出して移行先でもそのまま使う
 * - 移行先で同じ短縮コードがすでに使われている行は、取り込む前に利用者に扱いを選んでもらう
 */
final class LinkTransfer
{
    /** エクスポートする列（short_url は表で見たとき分かりやすいように付けるだけで、取り込みでは使わない） */
    public const EXPORT_COLUMNS = [
        'slug',
        'short_url',
        'url',
        'slug_type',
        'expires_at',
        'password_hash',
        'preview_mode',
        'preview_title',
        'preview_description',
        'preview_image_url',
        'click_count',
        'created_at',
    ];

    /** 取り込みで読む列（管理者向け CSV インポートのひな形で作った CSV も読めるよう password も受け付ける） */
    public const IMPORT_COLUMNS = [...self::EXPORT_COLUMNS, 'password'];

    public const MAX_ROWS = 500;

    public const SKIP = 'skip';

    public const OVERWRITE = 'overwrite';

    public const REISSUE = 'new';

    private const MAX_EXECUTION_SECONDS = 300;

    private const MAX_RANDOM_CODE_ATTEMPTS = 10;

    private const MAX_CLICK_COUNT = 1_000_000_000_000;

    public function __construct(
        private readonly ShortenerSettings $settings,
        private readonly SlugAvailability $availability,
        private readonly SlugGenerator $slugGenerator,
        private readonly IssuanceLimiter $limiter,
        private readonly ShortUrlBuilder $urls,
        private readonly ValidationFactory $validator,
    ) {}

    /** 利用者の（削除していない）短縮URLをすべて CSV にする。Excel で開いても文字化けしないよう BOM を付ける */
    public function export(User $user): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('CSV を作れませんでした。');
        }

        fwrite($handle, "\u{FEFF}");
        self::writeLine($handle, self::EXPORT_COLUMNS);

        $timezone = $this->settings->displayTimezone();

        ShortUrl::query()->ownedBy($user)->orderBy('id')->chunk(200, function ($links) use ($handle, $timezone): void {
            /** @var ShortUrl $link */
            foreach ($links as $link) {
                $custom = $link->preview_mode === PreviewMode::Custom;

                self::writeLine($handle, [
                    $link->slug,
                    $this->urls->url($link->slug),
                    $link->original_url,
                    $link->slug_type->value,
                    $link->expires_at?->setTimezone($timezone)->format('Y-m-d H:i:sP') ?? '',
                    $link->password_hash ?? '',
                    $link->preview_mode->value,
                    $custom ? (string) $link->preview_title : '',
                    $custom ? (string) $link->preview_description : '',
                    $custom ? (string) $link->preview_image_url : '',
                    (string) $link->click_count,
                    $link->created_at?->setTimezone($timezone)->format('Y-m-d H:i:sP') ?? '',
                ]);
            }
        });

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** CSV を読んで検証し、移行先で重なる短縮コードを洗い出す（まだ何も保存しない） */
    public function analyze(string $path, User $user): LinkTransferPlan
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return LinkTransferPlan::rejected('ファイルを読み込めませんでした。もう一度選び直してください。', line: 0);
        }

        $rows = CsvImporter::parse($contents);

        if ($rows === []) {
            return LinkTransferPlan::rejected('CSV が空です。発行履歴の「エクスポート」で書き出した CSV を選んでください。');
        }

        $header = array_map(static fn (string $value): string => strtolower(trim($value)), array_shift($rows));

        if (! in_array('url', $header, true)) {
            return LinkTransferPlan::rejected('1行目の見出しに url がありません。発行履歴の「エクスポート」で書き出した CSV を、1行目を残したまま選んでください。');
        }

        if (count($rows) > self::MAX_ROWS) {
            return LinkTransferPlan::rejected('データが'.count($rows).'行あります。一度に取り込めるのは'.self::MAX_ROWS.'行までです。分割してください。');
        }

        $accepted = [];
        $conflicts = [];
        $errors = [];
        // CSV の中で同じ短縮コードが 2 回出てきたら、2 回目以降は取り込まない（小文字にした値 => 最初の行番号）
        $seen = [];

        foreach ($rows as $index => $row) {
            // 見出しを 1 行目として数える
            $line = $index + 2;
            $values = self::combine($header, $row);

            if ($values === []) {
                continue;
            }

            $validator = $this->validator->make($values, $this->rules($user), $this->messages());

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $column => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = new CsvImportError($line, $column, $values[$column] ?? null, $message);
                    }
                }

                continue;
            }

            $normalized = $this->normalize($line, $validator->validated());
            $slug = $normalized['slug'];

            if ($slug !== null) {
                $key = mb_strtolower($slug);
                if (isset($seen[$key])) {
                    $errors[] = new CsvImportError($line, 'slug', $slug, "同じ短縮コードが{$seen[$key]}行目にもあります。1つにまとめてください。");

                    continue;
                }
                $seen[$key] = $line;

                $conflict = $this->conflictFor($line, $slug, SlugType::from($normalized['slug_type']), $normalized['url'], $user);
                if ($conflict !== null) {
                    $conflicts[$line] = $conflict;
                }
            }

            $accepted[] = $normalized;
        }

        return new LinkTransferPlan($accepted, $conflicts, $errors);
    }

    /**
     * 取り込む。重なっていた行は $resolutions（行番号 => skip / overwrite / new）に従う（選ばれていなければスキップ）
     *
     * @param  array<int, string>  $resolutions
     */
    public function apply(LinkTransferPlan $plan, User $user, array $resolutions = []): LinkTransferResult
    {
        @set_time_limit(self::MAX_EXECUTION_SECONDS);

        $quotaError = $this->checkMonthlyQuota($plan, $user, $resolutions);
        if ($quotaError !== null) {
            return LinkTransferResult::rejected($quotaError);
        }

        $imported = 0;
        $overwritten = 0;
        $reissued = 0;
        $skippedByChoice = 0;
        $errors = $plan->errors;

        foreach ($plan->rows as $row) {
            $line = (int) $row['line'];
            $conflict = $plan->conflicts[$line] ?? null;
            $resolution = $conflict === null ? null : ($resolutions[$line] ?? self::SKIP);

            try {
                if ($resolution === self::SKIP) {
                    $skippedByChoice++;
                } elseif ($resolution === self::OVERWRITE) {
                    $this->overwrite($row, $user);
                    $overwritten++;
                } elseif ($resolution === self::REISSUE || $row['slug'] === null) {
                    $this->insertWithRandomCode($row, $user);
                    $conflict === null ? $imported++ : $reissued++;
                } else {
                    $this->insertWithSlug($row, $user);
                    $imported++;
                }
            } catch (LinkTransferException $e) {
                $errors[] = new CsvImportError($line, $e->column, $e->column === null ? null : (string) ($row[$e->column] ?? ''), $e->getMessage());
            } catch (Throwable $e) {
                Log::error('発行履歴の取り込みで保存に失敗しました。', ['line' => $line, 'exception' => $e::class, 'error' => $e->getMessage()]);
                $errors[] = new CsvImportError($line, null, (string) $row['url'], '取り込めませんでした（サーバーのエラー。ログに詳細があります）。');
            }
        }

        usort($errors, static fn (CsvImportError $a, CsvImportError $b): int => $a->line <=> $b->line);

        $result = new LinkTransferResult($imported, $overwritten, $reissued, $skippedByChoice, $errors);

        Log::notice('発行履歴を CSV から取り込みました。', [
            'user_id' => $user->id,
            'imported' => $imported,
            'overwritten' => $overwritten,
            'reissued' => $reissued,
            'skipped_by_choice' => $skippedByChoice,
            'failed' => $result->skipped(),
        ]);

        return $result;
    }

    /** 移行先で同じ短縮コードが使えるか。使えなければその理由 */
    private function conflictFor(int $line, string $slug, SlugType $type, string $importUrl, User $user): ?LinkTransferConflict
    {
        $conflict = static fn (string $reason): LinkTransferConflict => new LinkTransferConflict($line, $slug, LinkTransferConflict::TAKEN, $reason, $importUrl);

        if ($this->availability->conflictsWithRoute($slug)) {
            return $conflict('このサーバーのページと同じ URL になるため、この短縮コードは使えません。');
        }

        $existing = ShortUrl::withTrashed()->where($this->sameCode($slug, $type))->first();

        if ($existing !== null) {
            if (! $existing->trashed() && $existing->user_id === $user->id && $existing->slug === $slug) {
                return new LinkTransferConflict($line, $slug, LinkTransferConflict::OWN, 'あなたの短縮URLがすでにこの短縮コードを使っています。', $importUrl, $existing->original_url);
            }

            return $conflict($existing->trashed()
                ? '削除された短縮URLの短縮コードは欠番のため使えません。'
                : 'ほかの短縮URLがすでにこの短縮コードを使っています。');
        }

        if (RetiredSlug::query()->where($this->sameCode($slug, $type))->exists()) {
            return $conflict('以前に使われていた短縮コードは欠番のため使えません。');
        }

        if (! $user->isAdmin() && $this->availability->isReserved($slug)) {
            return $conflict('この短縮コードは予約されているため使えません。');
        }

        return null;
    }

    /**
     * 同じ短縮URLとして届いてしまうコード（SlugAvailability と同じ考え方）。
     * ランダムコードは大文字小文字を区別せずに届くため、どちらかがランダムコードなら大文字小文字違いも重なりとみなす
     *
     * @return Closure(Builder<ShortUrl|RetiredSlug>): void
     */
    private function sameCode(string $slug, SlugType $type): Closure
    {
        $normalized = mb_strtolower($slug);

        return static function (Builder $query) use ($slug, $normalized, $type): void {
            $query->where('slug', $slug)->orWhere(static function (Builder $query) use ($normalized, $type): void {
                $query->where('slug_normalized', $normalized);
                if ($type !== SlugType::Random) {
                    $query->where('slug_type', SlugType::Random->value);
                }
            });
        };
    }

    /**
     * 管理者以外は、新しく増える件数が今月の発行上限に収まるときだけ取り込む
     *
     * @param  array<int, string>  $resolutions
     */
    private function checkMonthlyQuota(LinkTransferPlan $plan, User $user, array $resolutions): ?string
    {
        if ($user->isAdmin()) {
            return null;
        }

        $additions = 0;
        foreach ($plan->rows as $row) {
            $line = (int) $row['line'];
            $resolution = isset($plan->conflicts[$line]) ? ($resolutions[$line] ?? self::SKIP) : null;
            if ($resolution !== self::SKIP && $resolution !== self::OVERWRITE) {
                $additions++;
            }
        }

        $limit = $this->settings->memberMonthlyLimit();
        $remaining = max(0, $limit - $this->limiter->issuedThisMonth($user, '', CarbonImmutable::now()));

        return $additions > $remaining
            ? "新しく増える短縮URLが{$additions}件ありますが、今月はあと{$remaining}件までしか発行できません（{$limit}件/月）。CSV を分けて、来月に残りを取り込んでください。"
            : null;
    }

    /** @param  array<string, mixed>  $row */
    private function overwrite(array $row, User $user): void
    {
        $link = ShortUrl::query()->ownedBy($user)->where('slug', (string) $row['slug'])->first();

        if ($link === null) {
            throw new LinkTransferException('上書きするはずの短縮URLが見つかりませんでした（取り込みの途中で削除された可能性があります）。', 'slug');
        }

        $this->fill($link, $row);
        $link->save();
    }

    /** @param  array<string, mixed>  $row */
    private function insertWithSlug(array $row, User $user): void
    {
        $slug = (string) $row['slug'];
        $type = SlugType::from((string) $row['slug_type']);

        // 確認画面を出している間にほかの人が同じコードで発行した場合に備えて、保存の直前にもう一度確かめる
        $conflict = $this->conflictFor((int) $row['line'], $slug, $type, (string) $row['url'], $user);
        if ($conflict !== null) {
            throw new LinkTransferException($conflict->reason, 'slug');
        }

        $link = $this->newLink($row, $user);
        $link->slug = $slug;
        $link->slug_type = $type;

        try {
            DB::transaction(static fn () => $link->save());
        } catch (UniqueConstraintViolationException) {
            throw new LinkTransferException('ほかの短縮URLがすでにこの短縮コードを使っています。', 'slug');
        }
    }

    /** @param  array<string, mixed>  $row */
    private function insertWithRandomCode(array $row, User $user): void
    {
        $length = $this->settings->randomCodeLength();

        for ($attempt = 1; $attempt <= self::MAX_RANDOM_CODE_ATTEMPTS; $attempt++) {
            $code = $this->slugGenerator->generate($length);

            if ($this->availability->isReserved($code) || $this->availability->isTakenForRandom($code)) {
                continue;
            }

            $link = $this->newLink($row, $user);
            $link->slug = $code;
            $link->slug_type = SlugType::Random;

            try {
                DB::transaction(static fn () => $link->save());

                return;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new LinkTransferException('新しい短縮コードを作れませんでした。時間をおいて再度お試しください。');
    }

    /** @param  array<string, mixed>  $row */
    private function newLink(array $row, User $user): ShortUrl
    {
        $link = new ShortUrl;
        $link->user_id = $user->id;
        $link->creator_ip_hash = null;
        $link->deletion_token_hash = null;
        $link->click_count = (int) $row['click_count'];

        // 発行日は、管理者だけ元のサーバーの日時を引き継ぐ（それ以外は月間上限で数えられるよう取り込んだ日時にする）
        if ($user->isAdmin() && $row['created_at'] !== null) {
            $link->created_at = CarbonImmutable::parse((string) $row['created_at']);
        }

        $this->fill($link, $row);

        return $link;
    }

    /** 転送先・有効期限・パスワード・カードを CSV の値にする（上書きでも共通。クリック数と発行日は上書きしない） */
    private function fill(ShortUrl $link, array $row): void
    {
        $custom = $row['preview_mode'] === PreviewMode::Custom->value;

        $link->original_url = (string) $row['url'];
        $link->expires_at = $row['expires_at'] === null ? null : CarbonImmutable::parse((string) $row['expires_at']);
        $link->password_hash = $row['password_hash'];
        $link->preview_mode = PreviewMode::from((string) $row['preview_mode']);
        $link->preview_title = $custom ? $row['preview_title'] : null;
        $link->preview_description = $custom ? $row['preview_description'] : null;
        $link->preview_image_url = $custom ? $row['preview_image_url'] : null;
    }

    /**
     * 検証済みの値を、保存とキャッシュに使う形にそろえる（日時は UTC の ISO 8601）
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(int $line, array $values): array
    {
        $timezone = $this->settings->displayTimezone();
        $text = static function (string $key) use ($values): ?string {
            $value = $values[$key] ?? null;

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };
        $date = static function (string $key) use ($text, $timezone): ?string {
            $value = $text($key);

            // タイムゾーンの指定が無い日時は表示タイムゾーン（日本時間）として扱う
            return $value === null ? null : CarbonImmutable::parse($value, $timezone)->utc()->toIso8601String();
        };

        $password = $text('password');
        $slug = $text('slug');

        return [
            'line' => $line,
            'slug' => $slug,
            'slug_type' => $slug === null ? SlugType::Random->value : ($text('slug_type') ?? SlugType::Custom->value),
            'url' => (string) $text('url'),
            'expires_at' => $date('expires_at'),
            // 平文のパスワードが書かれていればそちらを優先する
            'password_hash' => $password !== null ? Hash::make($password) : $text('password_hash'),
            'preview_mode' => $text('preview_mode') ?? PreviewMode::Destination->value,
            'preview_title' => $text('preview_title'),
            'preview_description' => $text('preview_description'),
            'preview_image_url' => $text('preview_image_url'),
            'click_count' => (int) ($text('click_count') ?? 0),
            'created_at' => $date('created_at'),
        ];
    }

    /** @return array<string, list<mixed>> */
    private function rules(User $user): array
    {
        $basic = LinkRules::basic($this->settings, $user);

        return [
            'url' => $basic['url'],
            // 管理者以外は、元のサーバーでランダムコードだったものも含めてカスタムスラッグの最小文字数を守る
            'slug' => ['bail', 'nullable', 'string', 'min:'.$this->settings->customSlugMinLengthFor($user), 'max:'.ShortenerSettings::SLUG_COLUMN_LENGTH, 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'slug_type' => ['bail', 'nullable', Rule::enum(SlugType::class)],
            // 移行なので、期限切れのリンクもそのまま期限切れとして持っていける
            'expires_at' => ['bail', 'nullable', 'date'],
            'password' => $basic['password'],
            'password_hash' => ['bail', 'nullable', 'string', 'max:255', static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && ! self::isUsableHash(trim($value))) {
                    $fail('password_hash はこのサーバーで使えない形式です。エクスポートした値を変えずに入れてください。');
                }
            }],
            'click_count' => ['bail', 'nullable', 'integer', 'min:0', 'max:'.self::MAX_CLICK_COUNT],
            'created_at' => ['bail', 'nullable', 'date'],
        ] + LinkRules::preview();
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return LinkRules::messages() + [
            'slug_type.enum' => 'slug_type は random か custom を入れてください（空欄なら custom）。',
            'expires_at.date' => 'expires_at を日時として読めません。2026-12-31 23:59 のように入れてください。',
            'password_hash.string' => 'password_hash は文字で入れてください。',
            'password_hash.max' => 'password_hash が長すぎます。エクスポートした値を変えずに入れてください。',
            'click_count.integer' => 'click_count は 0 以上の整数を入れてください。',
            'click_count.min' => 'click_count は 0 以上の整数を入れてください。',
            'click_count.max' => 'click_count が大きすぎます。',
            'created_at.date' => 'created_at を日時として読めません。2026-12-31 23:59 のように入れてください。',
        ];
    }

    /** 移行先のハッシュ方式で照合できる値か（方式が違うと照合時にエラーになるため取り込まない） */
    private static function isUsableHash(string $hash): bool
    {
        $expected = match (config('hashing.driver', 'bcrypt')) {
            'argon' => 'argon2i',
            'argon2id' => 'argon2id',
            default => 'bcrypt',
        };

        return Hash::info($hash)['algoName'] === $expected;
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
            if (in_array($column, self::IMPORT_COLUMNS, true) && $column !== 'short_url') {
                $values[$column] = trim($row[$index] ?? '');
            }
        }

        return array_filter($values, static fn (string $value): bool => $value !== '') === [] ? [] : $values;
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $row
     */
    private static function writeLine($handle, array $row): void
    {
        fputcsv($handle, $row, ',', '"', '', "\r\n");
    }
}
