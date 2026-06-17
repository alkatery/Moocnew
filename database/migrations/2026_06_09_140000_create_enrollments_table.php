<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrollments (PRD §5.ج, §6) — deliberately decoupled from payments.
 *
 * `order_id` is nullable and carries NO foreign key in the MVP: in free
 * mode it stays null, and the constraint to the (Phase 2) `orders` table
 * is added only when the Commerce context is built. This keeps Enrollment
 * independent of Commerce (PRD §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('access_expires_at')->nullable(); // null = permanent
            $table->unsignedBigInteger('order_id')->nullable();  // Phase 2 link, no FK yet
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
