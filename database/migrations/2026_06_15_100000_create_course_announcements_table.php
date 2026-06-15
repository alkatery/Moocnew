<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعلانات المقرر (C3 — PRD §5.ط).
 *
 * كل إعلان يُنشئه طاقم المقرر يُخزَّن هنا بشكل دائم ويُعرض للمتعلّمين
 * الملتحقين. البريد الجماعي لا يُخزَّن (يُطابور مباشرةً — تقليل بيانات).
 * حذف المقرر يحذف إعلاناته (cascade)؛ حذف المؤلّف لا يحذف الإعلان (PDPL §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_announcements', function (Blueprint $table) {
            $table->id();

            // المقرر المالك — cascade: حذف المقرر يحذف إعلاناته
            $table->foreignId('course_id')
                ->constrained()
                ->cascadeOnDelete();

            // المؤلف — بلا cascade: نُبقي الإعلان حتى لو أُزيلت هوية المؤلف (PDPL)
            $table->foreignId('author_id')
                ->constrained('users');

            // العنوان والجسم نصّ عادي (لا HTML — منع XSS)
            $table->string('title', 255);
            $table->text('body');

            $table->timestamps();

            // فهرس لجلب إعلانات مقرر مرتّبة زمنياً بكفاءة
            $table->index(['course_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_announcements');
    }
};
