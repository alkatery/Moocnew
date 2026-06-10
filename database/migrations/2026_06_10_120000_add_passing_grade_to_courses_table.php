<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The minimum overall grade (%) a learner must reach across a course's
 * assessments to complete it and earn the certificate (the Edraak model).
 * 0 means ungraded: completion is gated on lessons alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedTinyInteger('passing_grade')->default(0)->after('price_minor');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('passing_grade');
        });
    }
};
