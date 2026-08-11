<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->text('url')->change();
            $table->text('secret')->change();

            $table->json('payload')
                ->nullable()
                ->after('events');

            $table->unsignedTinyInteger('delivery_attempts')
                ->default(0)
                ->after('payload');

            $table->timestamp('last_delivery_at')
                ->nullable()
                ->after('delivery_attempts');

            $table->string('status')
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn([
                'payload',
                'delivery_attempts',
                'last_delivery_at',
            ]);
        });
    }
};