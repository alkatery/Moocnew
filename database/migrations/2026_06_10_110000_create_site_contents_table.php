<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable site content (branding, logo/images, colours and all marketing
 * copy) managed from the admin panel. A free-form key/value store — distinct
 * from the typed, enum-keyed platform `settings` table (which holds
 * operational flags like the payments switch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group', 60)->index();
            $table->string('type', 20)->default('text'); // text|textarea|color|image|url
            $table->string('label');
            $table->text('value')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
