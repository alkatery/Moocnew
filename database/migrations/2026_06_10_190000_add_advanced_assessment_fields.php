<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced assessment (expert-practice batch):
 *  - quizzes.draw_count          — randomly draw N questions per attempt
 *    from the quiz's bank selection (anti-cheating variation).
 *  - quiz_attempts.question_ids  — the frozen random draw for an attempt,
 *    so resume/grading sees the same questions the learner saw.
 *  - question_bank.explanation   — shown to the learner AFTER submission
 *    as instant formative feedback ("لماذا هذه الإجابة").
 *  - assignments.rubric          — grading criteria [{id,title,max_points}].
 *  - assignment_submissions.rubric_scores — per-criterion awarded points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedSmallInteger('draw_count')->nullable()->after('shuffle');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->json('question_ids')->nullable()->after('user_id');
        });

        Schema::table('question_bank', function (Blueprint $table) {
            $table->text('explanation')->nullable()->after('correct');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->json('rubric')->nullable()->after('points');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->json('rubric_scores')->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', fn (Blueprint $t) => $t->dropColumn('draw_count'));
        Schema::table('quiz_attempts', fn (Blueprint $t) => $t->dropColumn('question_ids'));
        Schema::table('question_bank', fn (Blueprint $t) => $t->dropColumn('explanation'));
        Schema::table('assignments', fn (Blueprint $t) => $t->dropColumn('rubric'));
        Schema::table('assignment_submissions', fn (Blueprint $t) => $t->dropColumn('rubric_scores'));
    }
};
