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
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('counterparty_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->string('transaction_type')->default('payment');
            $table->string('channel')->default('card');
            $table->bigInteger('amount');
            $table->bigInteger('fee')->default(0);
            $table->bigInteger('net_amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->enum('status', [
                'pending',
                'success',
                'failed',
                'reversed',
                'abandoned',
            ])->default('pending');
            $table->string('description')->nullable();
            $table->string('gateway_response')->nullable();
            $table->json('authorization')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->enum('source', ['api', 'dashboard', 'system'])->default('api');
            $table->string('ip_address', 45)->nullable();
            $table->string('device')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['counterparty_business_id', 'created_at']);
            $table->index(['status']);
            $table->index(['transaction_type']);
            $table->index(['channel']);
            $table->index(['ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
