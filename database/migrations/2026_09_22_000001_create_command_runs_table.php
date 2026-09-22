<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panelden çalıştırılan komutların kaydı: kim, ne zaman, ne çalıştırdı, sonuç ne oldu.
 * Terminal erişimi olmayan bir sunucuda çıktıyı görmenin tek yolu budur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command_key', 60)->index();
            $table->string('command_line', 255);
            $table->string('mode', 20);                  // sync | background
            $table->string('status', 20)->index();       // queued | running | succeeded | failed
            $table->integer('exit_code')->nullable();
            $table->longText('output')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_runs');
    }
};
