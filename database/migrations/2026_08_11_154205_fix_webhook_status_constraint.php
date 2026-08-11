<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE webhooks
            DROP CONSTRAINT IF EXISTS webhooks_status_check
        ');

        DB::statement("
            ALTER TABLE webhooks
            ADD CONSTRAINT webhooks_status_check
            CHECK (status IN ('pending', 'success', 'failed'))
        ");

        DB::statement("
            ALTER TABLE webhooks
            ALTER COLUMN status SET DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE webhooks
            DROP CONSTRAINT IF EXISTS webhooks_status_check
        ');

        DB::statement("
            ALTER TABLE webhooks
            ADD CONSTRAINT webhooks_status_check
            CHECK (status IN ('active', 'inactive'))
        ");
    }
};
