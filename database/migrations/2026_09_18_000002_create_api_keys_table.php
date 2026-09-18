<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理者専用 API のキー（requirements.md 5）。平文は発行時に一度だけ表示し、SHA-256 のみ保存する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            // 一覧で見分けるための先頭部分（秘密ではない）
            $table->string('key_prefix', 16);
            $table->char('key_hash', 64)->unique();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
