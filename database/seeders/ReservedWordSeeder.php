<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReservedWordCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 予約語の初期リスト（requirements.md 2-2）。
 * 何度実行しても重複しないよう upsert する。以降の追加・削除はダッシュボードから行う想定。
 */
class ReservedWordSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const INITIAL_WORDS = [
        'system' => [
            'api', 'admin', 'kanri', 'dashboard', 'dash', 'redirect', 'www', 'mail', 'ftp', 'cdn',
            'app', 'static', 'assets', 'cron', 'cli', 'root', 'system', 'sys',
            // アプリのルート・設置フォルダ内のディレクトリと衝突する語
            'install', 'shorten', 'links', 'build', 'public', 'storage', 'vendor', 'bootstrap',
            'config', 'database', 'resources', 'routes', 'tests', 'scripts', 'node_modules', '_cron',
        ],
        'auth' => [
            'login', 'logout', 'register', 'signup', 'signin', 'signout', 'auth', 'oauth', 'callback',
            'password', 'reset', 'verify', 'token',
        ],
        'feature' => [
            'help', 'support', 'about', 'contact', 'terms', 'privacy', 'policy', 'faq', 'docs', 'blog',
            'news', 'status', 'health', 'ping', 'test', 'debug',
        ],
        'confusing' => [
            'null', 'undefined', 'none', 'delete', 'remove', 'error', '404', '500',
            'favicon', 'robots', 'sitemap', 'index', 'home',
        ],
        'custom' => [
            'choko1229', 'choko', '1229', 'choco', 'tyoko',
        ],
    ];

    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (self::INITIAL_WORDS as $category => $words) {
            $category = ReservedWordCategory::from($category);

            foreach ($words as $word) {
                $rows[] = [
                    'word' => mb_strtolower($word),
                    'category' => $category->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('reserved_words')->upsert($rows, ['word'], ['category', 'updated_at']);
    }
}
