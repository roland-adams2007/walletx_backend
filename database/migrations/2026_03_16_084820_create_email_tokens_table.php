<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_tokens', function (Blueprint $table) {

            $table->id();
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type'); // verify_email, reset_password etc
            $table->char('token_hash', 64)->unique();

            $table->boolean('is_used')->default(false);
            $table->dateTime('expires_at');

            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('used_at')->nullable();

            $table->index(['user_id', 'type', 'is_used', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_tokens');
    }
};
