<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول ربط التأليف الجماعي — E3 (PRD §6).
 * يربط course ↔ user بدور تأليفي (co_author في v1).
 * المالك (instructor_id) ليس صفّاً هنا؛ هو خاصيّة على courses.
 * لا soft-delete — الإزالة فعلية وقابلة لإعادة الإنشاء.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_members', function (Blueprint $table) {
            $table->id();

            // المقرر — تتالي الحذف عند حذف المقرر.
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            // المستخدم — تتالي الحذف عند حذف المستخدم.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // الدور التأليفي: v1 = 'co_author' فقط؛ عمود سلسلة (لا enum DB) يتيح النمو.
            $table->string('role')->default('co_author');

            $table->timestamps();

            // لا يُضاف نفس المستخدم مرّتين لنفس المقرر — طبقة ثانية خلف فحص المتحكّم.
            $table->unique(['course_id', 'user_id']);

            // فهرسة user_id — يخدم mine() وتتالي حذف المستخدم.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_members');
    }
};
