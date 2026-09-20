<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * お問い合わせ。メール送信を設定しなくても受け取れるよう、内容をここに保存し、
 * Discord Webhook が設定されていれば管理者へ通知する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table): void {
            $table->id();
            // ログイン中に送られた場合のみ。退会しても内容は残す
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 64)->nullable();
            // 返信先（メールアドレス・Discord のユーザー名など、送信者が任意で入力する）
            $table->string('reply_to', 190)->nullable();
            $table->text('message');
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();

            $table->index(['handled_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
