<?php

declare(strict_types=1);

use App\Enums\PreviewMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SNS に貼ったときのカード（OGP）の出し方を短縮URLごとに保存する */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('short_urls', function (Blueprint $table): void {
            $table->string('preview_mode', 16)->default(PreviewMode::Destination->value)->after('password_hash');
            $table->string('preview_title', 120)->nullable()->after('preview_mode');
            $table->string('preview_description', 300)->nullable()->after('preview_title');
            $table->string('preview_image_url', 2048)->nullable()->after('preview_description');
        });
    }

    public function down(): void
    {
        Schema::table('short_urls', function (Blueprint $table): void {
            $table->dropColumn(['preview_mode', 'preview_title', 'preview_description', 'preview_image_url']);
        });
    }
};
