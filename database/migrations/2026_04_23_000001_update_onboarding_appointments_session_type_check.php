<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE onboarding_appointments DROP CONSTRAINT IF EXISTS onboarding_appointments_session_type_check');
        DB::statement("ALTER TABLE onboarding_appointments ADD CONSTRAINT onboarding_appointments_session_type_check CHECK (session_type IN ('primary', 'incoming', 'supplemental', 'retraining'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE onboarding_appointments DROP CONSTRAINT IF EXISTS onboarding_appointments_session_type_check');
        DB::statement("ALTER TABLE onboarding_appointments ADD CONSTRAINT onboarding_appointments_session_type_check CHECK (session_type IN ('primary', 'supplemental', 'retraining'))");
    }
};
