<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real cover images (uploaded to the public disk) for courses and learning
 * paths, replacing the placeholder gradients when set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('summary');
        });

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};
