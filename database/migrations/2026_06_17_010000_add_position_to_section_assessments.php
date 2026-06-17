<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ترتيب موحّد لعناصر الوحدة (#3): تملك الدروس position داخل القسم منذ البداية،
 * وهنا نضيف نفس العمود للاختبارات والواجبات حتى يمكن إدراجها ضمن تسلسل
 * الوحدة الواحد (فيديو ← سؤال ← واجب ← اختبار…) وترتيبها بحرّية. القيمة 0
 * افتراضياً؛ تُطبّع نقطة إعادة الترتيب الموحّدة كل العناصر إلى 1..N.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(0)->after('weight');
        });

        Schema::table('assignments', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(0)->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn('position');
        });

        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
