<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates can now attest a whole learning path, not only a single
 * course: exactly one of (course_id, learning_path_id) is set per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->change();
            $table->foreignId('learning_path_id')
                ->nullable()
                ->constrained('learning_paths')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'learning_path_id']);
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'learning_path_id']);
            $table->dropConstrainedForeignId('learning_path_id');
            $table->foreignId('course_id')->nullable(false)->change();
        });
    }
};
