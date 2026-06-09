<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chart of accounts for the double-entry ledger (PRD §5.د). Platform-level
 * accounts (cash, revenue, refunds) are singletons; instructor payable
 * accounts are per-instructor (owner_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('owner_id')->nullable(); // instructor id for payable accounts
            $table->string('currency', 3)->default('SAR');
            $table->timestamps();

            $table->unique(['type', 'owner_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
