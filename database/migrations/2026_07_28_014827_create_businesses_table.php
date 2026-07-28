<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('alt_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->enum('business_type', ['individual', 'registered'])->default('individual');
            $table->string('industry')->nullable();
            $table->unsignedBigInteger('logo')->nullable();
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('pending_balance')->default(0);
            $table->bigInteger('max_balance')->nullable();
            $table->enum('kyc_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->timestamp('kyc_verified_at')->nullable();
            $table->string('settlement_bank_code')->nullable();
            $table->string('settlement_account_number')->nullable();
            $table->string('settlement_account_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
