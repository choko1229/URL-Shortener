<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 短縮URLにドメインと発行元を持たせる。
 * - domain: 空文字 = メインドメイン、それ以外は short_domains.host。短縮コードはドメインごとに一意にする
 * - source: page = 発行フォーム・CSV など / api = API で発行（月間発行数とは別に数える）
 * 変更前の短縮URLはすべてメインドメイン・page として扱う（API で発行したかは記録が無いため区別できない）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('short_urls', function (Blueprint $table): void {
            $table->string('domain', 255)->default('')->after('user_id');
            $table->string('source', 16)->default('page')->after('slug_type');
        });

        Schema::table('short_urls', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->unique(['domain', 'slug']);
            $table->index(['domain', 'slug_normalized']);
            $table->index(['user_id', 'source', 'created_at']);
        });

        Schema::table('retired_slugs', function (Blueprint $table): void {
            $table->string('domain', 255)->default('')->after('id');
        });

        Schema::table('retired_slugs', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->unique(['domain', 'slug']);
            $table->index(['domain', 'slug_normalized']);
        });
    }

    public function down(): void
    {
        Schema::table('retired_slugs', function (Blueprint $table): void {
            $table->dropIndex(['domain', 'slug_normalized']);
            $table->dropUnique(['domain', 'slug']);
            $table->unique(['slug']);
        });

        Schema::table('retired_slugs', function (Blueprint $table): void {
            $table->dropColumn('domain');
        });

        Schema::table('short_urls', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'source', 'created_at']);
            $table->dropIndex(['domain', 'slug_normalized']);
            $table->dropUnique(['domain', 'slug']);
            $table->unique(['slug']);
        });

        Schema::table('short_urls', function (Blueprint $table): void {
            $table->dropColumn(['domain', 'source']);
        });
    }
};
