<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE onboarding_requests
            DROP CONSTRAINT IF EXISTS onboarding_requests_status_check
        ");

        DB::statement("
            ALTER TABLE onboarding_requests
            ADD CONSTRAINT onboarding_requests_status_check
            CHECK (status IN ('pending', 'in_progress', 'on_hold', 'revision_requested', 'completed', 'cancelled'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE onboarding_requests
            DROP CONSTRAINT IF EXISTS onboarding_requests_status_check
        ");

        DB::statement("
            ALTER TABLE onboarding_requests
            ADD CONSTRAINT onboarding_requests_status_check
            CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled'))
        ");
    }
};
