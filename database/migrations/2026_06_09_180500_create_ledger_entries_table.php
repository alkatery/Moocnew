<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry ledger lines (PRD §5.د): every financial event is a
 * transaction (transaction_uuid) whose entries' debits and credits balance.
 * Each line is debit XOR credit. Amounts are integer minor units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid');
            $table->foreignId('account_id')->constrained('ledger_accounts')->cascadeOnDelete();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('memo')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('transaction_uuid');
            $table->index(['account_id']);
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
