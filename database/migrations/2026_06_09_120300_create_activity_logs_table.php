<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail (PRD §5.ي, §6). Records significant events with a
 * timestamp and the acting user. IP is intentionally not stored here:
 * PDPL favours data minimisation, and IP is kept only where a specific
 * security need and retention policy justify it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->nullableMorphs('subject');
            $table->jsonb('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['causer_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
