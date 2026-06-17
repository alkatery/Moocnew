<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول ربط ذاتي على courses — E1 المتطلّبات السابقة (PRD §5.ج).
 * المقرر يشترط إكمال مقرر آخر قبل الالتحاق. لا soft-delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->id();
            // المقرر الذي يشترط وجود متطلّب.
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();
            // المقرر المطلوب إكماله قبل الالتحاق بـ course_id.
            $table->foreignId('prerequisite_course_id')
                ->constrained('courses')
                ->cascadeOnDelete();
            $table->timestamps();

            // لا يُكرَّر نفس الزوج — طبقة ثانية خلف syncWithoutDetaching.
            $table->unique(['course_id', 'prerequisite_course_id']);
            // فهرسة عكسية: «أي مقررات تشترطني؟» — يخدم CASCADE والاستعلامات العكسية.
            $table->index('prerequisite_course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
    }
};
