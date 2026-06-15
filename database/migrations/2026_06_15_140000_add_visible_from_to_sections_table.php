<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2 — جدولة ظهور الأقسام: يضيف حقل visible_from لتحديد متى يظهر القسم
 * للطالب. null = ظاهر دائماً (السلوك الافتراضي لكل الأقسام القائمة).
 * قيمة مستقبلية = مخفيّ عن الطالب حتى الموعد، ظاهر للطاقم (معاينة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            // تاريخ ظهور القسم للطالب. null = ظاهر دائماً (سلوك كل الأقسام القائمة).
            // قيمة مستقبلية = مخفيّ عن الطالب حتى الموعد، ظاهر للطاقم (معاينة).
            $table->timestamp('visible_from')->nullable()->after('position');

            // يخدم scopeVisibleTo/visibleNow ضمن المقرر (البادئة course_id تعجّل الفلترة الزمنية).
            $table->index(['course_id', 'visible_from']);
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'visible_from']);
            $table->dropColumn('visible_from');
        });
    }
};
