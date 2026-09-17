<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理画面から変更する設定値（発行上限などの業務ルール、GitHub トークン等の機密値）。
 * 機密値は APP_KEY で暗号化して保存する（requirements.md 7-2, 10）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            // JSON エンコードした値。is_encrypted が true の場合は暗号文
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
