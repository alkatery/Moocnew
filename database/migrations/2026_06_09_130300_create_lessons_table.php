<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lessons (PRD §6). A lesson is a video, article, file, or live session.
 * Video lessons carry the managed-service `video_id` and a `video_status`
 * driven by the provider webhook. `is_free_preview` lets a lesson be shown
 * to non-enrolled visitors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->text('content')->nullable();       // article body
            $table->string('asset_path')->nullable();  // file/document storage key
            $table->string('video_id')->nullable();    // managed video service id
            $table->string('video_status')->default('none');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_free_preview')->default(false);
            $table->timestamps();

            $table->index(['section_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
