<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_link_rules', function (Blueprint $table) {
            $table->id();
            $table->string('anchor_text', 190);
            $table->string('target_url', 500);
            $table->string('target_hash', 32)->index();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->unsignedSmallInteger('max_per_article')->default(1);
            // all | blog | service | district | province | page
            $table->string('scope_type', 20)->default('blog')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('applied_count')->default(0);
            $table->timestamps();

            $table->unique(['anchor_text', 'target_hash']);
        });

        // Hedef başına anchor havuzu + sahiplik (spec §2)
        Schema::create('internal_link_registry', function (Blueprint $table) {
            $table->id();
            $table->string('target_url', 500);
            $table->string('target_hash', 32)->unique();
            $table->string('owner_keyword', 190)->nullable();
            $table->json('anchor_pool')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });

        // Reddedilen öneri BİR DAHA ÇIKMAZ (spec §3.6)
        Schema::create('internal_link_suggestion_rejects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blogs')->cascadeOnDelete();
            $table->string('target_url', 500);
            $table->string('anchor_text', 190);
            $table->string('row_hash', 32)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_link_suggestion_rejects');
        Schema::dropIfExists('internal_link_registry');
        Schema::dropIfExists('internal_link_rules');
    }
};
