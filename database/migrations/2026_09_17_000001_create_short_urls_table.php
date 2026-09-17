<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_urls', function (Blueprint $table): void {
            $table->id();
            // 未ログイン発行分は null（requirements.md 2-6: データは記録し管理者のみ閲覧）
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // カスタムスラッグは大文字小文字を区別する（requirements.md 2-1）ため、MySQL ではバイナリ照合順序で一意制約を張る。
            // 論理削除した行も残るので、削除済みコードは自動的に「欠番」になる（requirements.md 2-4）。
            $slug = $table->string('slug', 20);
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $slug->collation('utf8mb4_bin');
            }
            $slug->unique();

            // ランダムコードの「大文字小文字を区別しない重複判定」用に小文字化した値を持つ
            $table->string('slug_normalized', 20)->index();
            $table->string('slug_type', 16);

            $table->text('original_url');
            $table->string('password_hash')->nullable();
            // 未ログイン発行者向けの削除用シークレットトークン（平文は保存せず SHA-256 のみ）
            $table->char('deletion_token_hash', 64)->nullable()->unique();

            // null = 無期限。TIMESTAMP 型の 2038 年問題を避けるため DATETIME（UTC）で保持する
            $table->dateTime('expires_at')->nullable()->index();

            $table->unsignedBigInteger('click_count')->default(0);
            $table->dateTime('last_clicked_at')->nullable();

            // 未ログイン発行の月間上限（IPベース）判定用。生IPは保存せず HMAC ハッシュのみ
            $table->char('creator_ip_hash', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['creator_ip_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_urls');
    }
};
