<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('url');
            $table->string('secret')->nullable(); // for signing webhook requests
            $table->json('events'); // e.g. ['charge.success', 'transfer.success']
            $table->enum('status', ['active', 'inactive', 'failed'])->default('active');
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
