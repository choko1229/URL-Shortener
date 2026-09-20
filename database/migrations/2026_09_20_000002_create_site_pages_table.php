<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 設置した人が用意する固定ページ（利用規約・プライバシーポリシー）。
 * 本文は Markdown で保存し、表示時に HTML へ変換する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('title', 100);
            $table->longText('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
