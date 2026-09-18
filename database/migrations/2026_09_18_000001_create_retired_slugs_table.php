<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * カスタムスラッグの編集で使われなくなった短縮コード。
 * 削除したコードと同様に欠番として扱い、再利用させない（requirements.md 2-4）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retired_slugs', function (Blueprint $table): void {
            $table->id();

            $slug = $table->string('slug', 20);
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $slug->collation('utf8mb4_bin');
            }
            $slug->unique();

            $table->string('slug_normalized', 20)->index();
            $table->string('slug_type', 16);
            $table->foreignId('short_url_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('retired_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_slugs');
    }
};
