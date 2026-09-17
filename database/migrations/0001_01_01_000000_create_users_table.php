<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 認証は Discord ログインのみ（requirements.md 4-1）のため、email / password 列は持たない。
 * 取得スコープは identify のみなので、保存するのは Discord のユーザーID・ユーザー名・表示名・アバターのハッシュだけ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            // Discord のスノーフレークIDは 64bit 整数だが、JS 等での桁落ちを避けるため文字列で保持する
            $table->string('discord_id', 32)->unique();
            $table->string('username', 64);
            $table->string('global_name', 64)->nullable();
            $table->string('avatar_hash', 64)->nullable();
            $table->string('role', 16)->default('member')->index();
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            // 退会は論理削除。発行済みURLの記録は残す（requirements.md 2-4, 4-4）
            $table->softDeletes();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
