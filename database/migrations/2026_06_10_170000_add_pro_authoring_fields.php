<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Professional authoring upgrade (studio v2):
 *  - lessons.transcript      — editable video transcript shown in the player.
 *  - quizzes/assignments     — `weight` for the weighted course grade and an
 *    optional `section_id` so each assessment can live inside a curriculum
 *    section ("درجات كل قسم").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->text('transcript')->nullable()->after('content');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedSmallInteger('weight')->default(1)->after('pass_mark');
            $table->foreignId('section_id')->nullable()->after('course_id')
                ->constrained('sections')->nullOnDelete();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('weight')->default(1)->after('points');
            $table->foreignId('section_id')->nullable()->after('course_id')
                ->constrained('sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('transcript');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
            $table->dropColumn('weight');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
            $table->dropColumn('weight');
        });
    }
};
