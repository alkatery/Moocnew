<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامات مرجعية يحفظها المتعلّم على الدروس للرجوع إليها لاحقاً (D1).
 * البنية مرآة lesson_notes بلا at_seconds/body، مع قيد تفرّد (user, lesson).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            // علامة واحدة لكل (مستخدم، درس) — طبقة دفاع ثانية خلف firstOrCreate
            // يُفهرس استعلام index (where user_id) أيضاً دون حاجة لفهرس إضافي
            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
