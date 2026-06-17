<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video interaction batch:
 *  - lesson_notes        — a learner's private notes on a lesson, optionally
 *    anchored to a video timestamp (click → seek).
 *  - lessons.checkpoints — in-video formative questions
 *    [{at_seconds, question_id}] that pause the player and check
 *    understanding without affecting the grade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('at_seconds')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->index(['user_id', 'lesson_id']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->json('checkpoints')->nullable()->after('transcript');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_notes');
        Schema::table('lessons', fn (Blueprint $t) => $t->dropColumn('checkpoints'));
    }
};
