<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بوّابة الوحدة (#3): اختبار يُعلَّم كـ «بوّابة» لقسمه، فلا تُفتح الوحدات
 * التالية للمتعلّم حتى يجتازه. اختبار واحد لكل وحدة كحدّ أقصى منطقياً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->boolean('is_gate')->default(false)->after('section_id');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn('is_gate');
        });
    }
};
