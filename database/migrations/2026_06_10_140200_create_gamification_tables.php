<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gamification: a running points total and learning-day streak per learner,
 * an append-only ledger of point awards (idempotent per source), and the
 * badges a learner has earned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_active_on')->nullable();
            $table->timestamps();
        });

        Schema::create('point_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 40);          // lesson_completed, course_completed, …
            $table->unsignedInteger('points');
            $table->string('source_key')->nullable(); // idempotency, e.g. "lesson:42"
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'source_key']);
            $table->index('user_id');
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge');                // first_course, streak_7, points_1000, …
            $table->timestamp('earned_at')->useCurrent();

            $table->unique(['user_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
        Schema::dropIfExists('point_awards');
        Schema::dropIfExists('learner_stats');
    }
};
