<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D2 — إضافة حقل الإجابة المقبولة على موضوعات المنتدى (PRD §5.ز). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_threads', function (Blueprint $table) {
            // إشارة واحدة للإجابة المقبولة؛ nullOnDelete حتى لا يُحذف الموضوع بحذف الرد
            $table->foreignId('accepted_post_id')
                ->nullable()
                ->after('locked')
                ->constrained('forum_posts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_post_id');
        });
    }
};
