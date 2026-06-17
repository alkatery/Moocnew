<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable question bank per course (PRD §5.هـ). `choices` holds the
 * options for MCQs; `correct` holds the answer key (choice ids for MCQ, a
 * boolean for true/false, or accepted strings for short answers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('body');
            $table->jsonb('choices')->nullable();
            $table->jsonb('correct');
            $table->unsignedInteger('points')->default(1);
            $table->timestamps();

            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank');
    }
};
