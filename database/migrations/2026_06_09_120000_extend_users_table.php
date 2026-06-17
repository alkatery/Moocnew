<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the default users table with the Identity profile fields from
 * PRD §5.أ, plus soft deletes (users are a sensitive entity, PRD §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');        // E.164
            $table->string('country', 2)->nullable()->after('phone');   // ISO 3166-1 alpha-2
            $table->string('education_level')->nullable()->after('country');
            $table->jsonb('interests')->nullable()->after('education_level');
            $table->string('locale', 5)->default('ar')->after('interests');
            $table->string('timezone')->default('Asia/Riyadh')->after('locale');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone',
                'country',
                'education_level',
                'interests',
                'locale',
                'timezone',
            ]);
        });
    }
};
