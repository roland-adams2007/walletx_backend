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
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('file_original_name');
            $table->string('file_name');
            $table->string('public_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('extension', 20);
            $table->string('type', 50);
            $table->bigInteger('file_size');
            $table->timestamps();
            $table->index('user_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
