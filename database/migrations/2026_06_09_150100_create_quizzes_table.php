<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quizzes (PRD §5.هـ): a timed, optionally shuffled set of questions drawn
 * from the course's question bank, with a pass mark and attempt cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->boolean('shuffle')->default(true);
            $table->unsignedInteger('max_attempts')->nullable(); // null = unlimited
            $table->unsignedTinyInteger('pass_mark')->default(60); // percent
            $table->timestamps();

            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
