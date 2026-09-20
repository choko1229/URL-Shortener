<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * 利用規約・プライバシーポリシーなどの固定ページ（本文は Markdown）。
 * 未作成のページは公開せず（404）、フッターにも出さない。
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property CarbonImmutable|null $updated_at
 */
class SitePage extends Model
{
    public const TERMS = 'terms';

    public const PRIVACY = 'privacy';

    /** @var array<string, string> 用意できるページ（slug => 既定のタイトル） */
    public const AVAILABLE = [
        self::TERMS => '利用規約',
        self::PRIVACY => 'プライバシーポリシー',
    ];

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'title',
        'body',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * フッターに出すページ（slug => タイトル）。データベースを読めない場合は空
     *
     * @return array<string, string>
     */
    public static function menu(): array
    {
        try {
            /** @var array<string, string> $titles */
            $titles = static::query()
                ->whereIn('slug', array_keys(self::AVAILABLE))
                ->pluck('title', 'slug')
                ->all();

            // 表示順は AVAILABLE の並びに合わせる
            $menu = [];
            foreach (array_keys(self::AVAILABLE) as $slug) {
                if (isset($titles[$slug])) {
                    $menu[$slug] = $titles[$slug];
                }
            }

            return $menu;
        } catch (QueryException $e) {
            Log::debug('固定ページを読み込めませんでした。', ['exception' => $e::class]);

            return [];
        }
    }

    /** Markdown を HTML にする。本文に書かれた HTML は取り除く（管理者の入力ミスや貼り付け事故への備え） */
    public function renderedBody(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }
}
