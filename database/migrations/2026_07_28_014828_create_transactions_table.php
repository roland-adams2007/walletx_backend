<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('access_code')->unique()->nullable();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('counterparty_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['credit', 'debit']);
            $table->enum('channel', ['card', 'bank_transfer', 'ussd', 'transfer', 'wallet', 'reversal'])->default('card');

            $table->bigInteger('amount');           // in kobo/cents, excludes fee
            $table->bigInteger('fee')->default(0);   // charged on this transaction
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');

            $table->enum('status', ['pending', 'success', 'failed', 'reversed', 'abandoned'])->default('pending');
            $table->string('description')->nullable();
            $table->string('gateway_response')->nullable(); // raw processor/bank message

            $table->json('authorization')->nullable(); // tokenized card/account details for reuse
            $table->json('meta')->nullable();

            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->enum('source', ['api', 'dashboard', 'system'])->default('api');

            // Device / audit trail
            $table->string('ip_address', 45)->nullable();
            $table->string('device')->nullable();     // e.g. "iPhone 15 / iOS 18" or device fingerprint
            $table->text('user_agent')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index(['counterparty_business_id', 'created_at']);
            $table->index(['status']);
            $table->index(['ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
