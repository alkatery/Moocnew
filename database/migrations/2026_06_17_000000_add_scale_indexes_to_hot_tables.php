<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس أداء لمسارات استعلام ساخنة تحت الحمل الكبير (أعداد طلاب هائلة).
 *
 * إضافية بحتة — لا تغيّر أي مخطّط ولا سلوك، فقط تُسرّع الاستعلامات القائمة:
 *
 *   • enrollments(course_id, status): كشف ملتحقي المقرر في سجلّ الدرجات
 *     (GradebookService::enrolledStudents) وعدّ الالتحاقات لكل مقرر
 *     (AnalyticsService). العمود course_id كان ثانوياً في unique(user_id,
 *     course_id) فلا يستطيع Postgres استخدامه لمسند course_id وحده — بقيّة
 *     الجداول (sections/quizzes/…) تُفهرس course_id، وهذا الجدول كان الشاذّ.
 *
 *   • learner_stats(points): لوحة المتصدّرين (orderByDesc(points) limit 20)
 *     وحساب رتبة المتعلّم (where points > X then count) في GamificationController.
 *     بلا فهرس كان كلّ نداء يفرز الجدول كاملاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->index(['course_id', 'status'], 'enrollments_course_id_status_index');
        });

        Schema::table('learner_stats', function (Blueprint $table): void {
            $table->index('points', 'learner_stats_points_index');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex('enrollments_course_id_status_index');
        });

        Schema::table('learner_stats', function (Blueprint $table): void {
            $table->dropIndex('learner_stats_points_index');
        });
    }
};
