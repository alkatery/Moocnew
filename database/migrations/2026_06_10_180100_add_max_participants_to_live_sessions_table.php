<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NELC compliance: synchronous (live) sessions are capped at 35 learners.
 * `max_participants` is the regulatory ceiling; the optional `capacity`
 * remains the instructor-chosen seat count (validated to stay ≤ 35).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->unsignedInteger('max_participants')->default(35)->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn('max_participants');
        });
    }
};
