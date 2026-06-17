<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D2 — جدول اشتراكات متابعة موضوعات المنتدى (PRD §5.ز). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_subscriptions', function (Blueprint $table) {
            $table->id();
            // حذف الموضوع يحذف اشتراكاته تلقائياً
            $table->foreignId('thread_id')
                ->constrained('forum_threads')
                ->cascadeOnDelete();
            // تقاعد/حذف المستخدم يحذف اشتراكاته (PDPL — تقليل البيانات)
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            // قيد التفرّد: اشتراك واحد لكل (موضوع، مستخدم) + فهرس ضمني
            $table->unique(['thread_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_subscriptions');
    }
};
