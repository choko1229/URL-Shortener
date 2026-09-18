<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 自動アップデートの実行履歴（requirements.md 7 章） */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('from_version', 32);
            $table->string('to_version', 32);
            $table->string('strategy', 16);
            $table->string('status', 24)->index();
            $table->text('message')->nullable();
            $table->string('backup_path')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_runs');
    }
};
