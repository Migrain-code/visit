<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 60)->index();
            $table->string('content_type', 60)->nullable();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('batch_key', 64)->nullable()->index();
            $table->string('model', 120);
            $table->string('status', 20)->default('running')->index();
            $table->json('input')->nullable();
            $table->longText('output')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
