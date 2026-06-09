<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments against orders via the aggregator (Moyasar). The gateway
 * reference is unique to enforce webhook idempotency (PRD §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('moyasar');
            $table->string('gateway_ref')->nullable();
            $table->string('status')->default('initiated');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('SAR');
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
