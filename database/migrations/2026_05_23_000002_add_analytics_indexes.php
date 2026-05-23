<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statements = [
            'CREATE INDEX IF NOT EXISTS appointments_status_scheduled_date_idx ON appointments (status, scheduled_date)',
            'CREATE INDEX IF NOT EXISTS appointments_trainer_scheduled_date_idx ON appointments (trainer_id, scheduled_date)',
            'CREATE INDEX IF NOT EXISTS appointments_creator_scheduled_date_idx ON appointments (creator_id, scheduled_date)',

            'CREATE INDEX IF NOT EXISTS onboarding_requests_status_created_at_idx ON onboarding_requests (status, created_at)',
            'CREATE INDEX IF NOT EXISTS onboarding_requests_status_completed_at_idx ON onboarding_requests (status, completed_at)',
            'CREATE INDEX IF NOT EXISTS onboarding_requests_trainer_status_idx ON onboarding_requests (trainer_id, status)',

            'CREATE INDEX IF NOT EXISTS onboarding_client_feedbacks_submitted_at_idx ON onboarding_client_feedbacks (submitted_at)',
            'CREATE INDEX IF NOT EXISTS onboarding_client_feedbacks_rating_idx ON onboarding_client_feedbacks (rating)',

            'CREATE INDEX IF NOT EXISTS appointment_feedback_submitted_at_idx ON appointment_feedback (submitted_at)',
            'CREATE INDEX IF NOT EXISTS appointment_feedback_rating_idx ON appointment_feedback (rating)',
            'CREATE INDEX IF NOT EXISTS appointment_feedback_appointment_submitted_idx ON appointment_feedback (appointment_id, submitted_at)',

            'CREATE INDEX IF NOT EXISTS appointment_students_appointment_attendance_idx ON appointment_students (appointment_id, attendance_status)',

            'CREATE INDEX IF NOT EXISTS telegram_messages_sent_at_idx ON telegram_messages (sent_at)',
            'CREATE INDEX IF NOT EXISTS onboarding_lessons_sent_at_idx ON onboarding_lessons (sent_at)',

            'CREATE INDEX IF NOT EXISTS onboarding_trainer_assignments_onboarding_idx ON onboarding_trainer_assignments (onboarding_id, assigned_at)',
        ];

        foreach ($statements as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        $indexes = [
            'appointments_status_scheduled_date_idx',
            'appointments_trainer_scheduled_date_idx',
            'appointments_creator_scheduled_date_idx',
            'onboarding_requests_status_created_at_idx',
            'onboarding_requests_status_completed_at_idx',
            'onboarding_requests_trainer_status_idx',
            'onboarding_client_feedbacks_submitted_at_idx',
            'onboarding_client_feedbacks_rating_idx',
            'appointment_feedback_submitted_at_idx',
            'appointment_feedback_rating_idx',
            'appointment_feedback_appointment_submitted_idx',
            'appointment_students_appointment_attendance_idx',
            'telegram_messages_sent_at_idx',
            'onboarding_lessons_sent_at_idx',
            'onboarding_trainer_assignments_onboarding_idx',
        ];

        foreach ($indexes as $idx) {
            DB::statement("DROP INDEX IF EXISTS {$idx}");
        }
    }
};
