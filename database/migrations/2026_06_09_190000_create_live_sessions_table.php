<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live sessions (PRD §5.و): scheduled classes tied to a course, delivered
 * via Zoom, Google Meet, or a plain external link. Seats are capacity-bound.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('provider'); // zoom | google_meet | manual
            $table->string('join_url')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable(); // null = unlimited
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'starts_at']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};
