<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NELC-mandated learner satisfaction surveys: four 1–5 axes (overall,
 * content, instructor, platform) plus an optional comment, answered once
 * per (course, learner) after completing the course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('overall'); // 1..5
            $table->unsignedTinyInteger('content_quality'); // 1..5
            $table->unsignedTinyInteger('instructor_quality'); // 1..5
            $table->unsignedTinyInteger('platform_quality'); // 1..5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_surveys');
    }
};
