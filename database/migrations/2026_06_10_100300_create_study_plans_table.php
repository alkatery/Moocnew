<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal study plans: the learner gathers courses into a list with a
 * reminder cadence; the platform keeps nudging them until it's done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->unsignedSmallInteger('cadence_days')->default(3);
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_reminded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plans');
    }
};
