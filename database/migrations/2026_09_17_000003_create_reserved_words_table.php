<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 予約語（requirements.md 2-2）。ダッシュボードから追加できるよう DB で管理する。
 * 初期データは Database\Seeders\ReservedWordSeeder で投入する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserved_words', function (Blueprint $table): void {
            $table->id();
            // 小文字に正規化して保存する（App\Models\ReservedWord）
            $table->string('word', 20)->unique();
            $table->string('category', 16)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_words');
    }
};
