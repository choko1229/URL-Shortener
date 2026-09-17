<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * アクセス記録（requirements.md 2-6: クリック数・リファラ・国・デバイス種別）。
 * プライバシー配慮のため IP アドレス・User-Agent 全文・リファラのパス/クエリは保存しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_url_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('short_url_id')->constrained()->cascadeOnDelete();
            $table->string('referrer_host', 255)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('device_type', 16);
            $table->dateTime('clicked_at');

            $table->index(['short_url_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_url_clicks');
    }
};
