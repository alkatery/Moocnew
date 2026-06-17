<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A video lesson names its source provider (PRD §5.ب). Videos may be hosted
 * on the managed Bunny Stream service, served from our own S3-compatible
 * storage via a signed URL, or embedded from YouTube. `video_id` holds the
 * provider-specific identifier (Bunny GUID, storage object key, or YouTube
 * id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_provider')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('video_provider');
        });
    }
};
