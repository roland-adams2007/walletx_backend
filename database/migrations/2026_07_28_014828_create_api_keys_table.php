<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('environment', ['live', 'test'])->default('test');
            $table->string('public_key')->unique();
            $table->text('secret_key')->unique();
            $table->enum('status', ['active', 'inactive', 'revoked'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['public_key']);
            $table->index(['secret_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};