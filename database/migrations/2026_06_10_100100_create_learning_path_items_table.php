<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The courses inside a learning path. `level` groups courses into stages
 * (المستوى الأول، الثاني…) and `position` orders courses within a level;
 * the pair defines the strict sequence learners must follow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_path_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();

            $table->unique(['learning_path_id', 'course_id']);
            $table->index(['learning_path_id', 'level', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_items');
    }
};
