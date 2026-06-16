<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E4 — إضافة عمود config لمعاملات تقييم الأنواع الجديدة
 * (tolerance للرقمي، flags للـ Regex). فارغ للأنواع القائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_bank', function (Blueprint $table) {
            // معاملات تقييم غير-إجابة للأنواع الجديدة (tolerance/flags). فارغ للأنواع القائمة.
            $table->jsonb('config')->nullable()->after('correct');
        });
    }

    public function down(): void
    {
        Schema::table('question_bank', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
};
