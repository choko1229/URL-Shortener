<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 短縮URLに使える追加のドメイン（go.example.com など）。管理画面で追加する。
 * メインドメイン（SHORTENER_SHORT_URL_BASE）はここには入れず、短縮URLの domain 列では空文字で表す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_domains', function (Blueprint $table): void {
            $table->id();
            // 小文字のホスト名（ポートは含めない）
            $table->string('host', 255)->unique();
            // page: 発行フォームと API の両方で使える / api: API からのみ発行できる
            $table->string('mode', 16)->default('page');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_domains');
    }
};
