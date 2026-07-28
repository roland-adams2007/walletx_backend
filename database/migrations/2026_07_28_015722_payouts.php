<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete(); // the debit record once processed
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete(); // null if automatic
            $table->enum('source', ['manual', 'automatic'])->default('manual');

            $table->bigInteger('amount');
            $table->bigInteger('fee')->default(0);
            $table->string('bank_code');
            $table->string('account_number');
            $table->string('account_name');
            $table->string('narration')->nullable();

            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'reversed'])->default('pending');
            $table->string('gateway_reference')->nullable();
            $table->string('gateway_response')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);

            $table->json('meta')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('device')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
