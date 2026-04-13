<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL enums are implemented as VARCHAR + CHECK constraint.
        // Drop the old constraint and replace it with one that includes 'pending'.
        DB::statement("
            ALTER TABLE onboarding_requests
            DROP CONSTRAINT IF EXISTS onboarding_requests_status_check
        ");

        DB::statement("
            ALTER TABLE onboarding_requests
            ADD CONSTRAINT onboarding_requests_status_check
            CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled'))
        ");

        DB::statement("
            ALTER TABLE onboarding_requests
            ALTER COLUMN status SET DEFAULT 'pending'
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
            CHECK (status IN ('in_progress', 'completed', 'cancelled'))
        ");

        DB::statement("
            ALTER TABLE onboarding_requests
            ALTER COLUMN status SET DEFAULT 'in_progress'
        ");
    }
};
