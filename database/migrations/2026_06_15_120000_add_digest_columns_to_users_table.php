<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D3 — إضافة بُعد تكرار الملخّص على المستخدم.
 *
 * digest_frequency: تفضيل المستخدم للتجميع (off/daily/weekly).
 * last_digest_at:   وقت آخر ملخّص مُرسَل — يضمن idempotency (لا ازدواج).
 * الفهرس المركّب يخدم استعلام اختيار المستحقّين في الأمر المجدول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // تردّد الملخّص: off (افتراضي) لا يغيّر سلوك المستخدمين الحاليين
            $table->string('digest_frequency')
                ->default('off')
                ->after('disabled_at');

            // وقت آخر إرسال — null يعني لم يُرسَل بعد
            $table->timestamp('last_digest_at')
                ->nullable()
                ->after('digest_frequency');

            // فهرس مركّب يسرّع: WHERE digest_frequency != 'off' AND last_digest_at ...
            $table->index(['digest_frequency', 'last_digest_at'], 'users_digest_frequency_last_digest_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_digest_frequency_last_digest_at_index');
            $table->dropColumn(['digest_frequency', 'last_digest_at']);
        });
    }
};
