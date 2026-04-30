<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\OnboardingRequest;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds test data for the 6 Horizon automated notification jobs.
 *
 * Run once, then trigger each job manually:
 *   php artisan appointment:send-reminders      → fires jobs 1 & 2
 *   php artisan appointment:check-no-show       → fires job 3
 *   php artisan onboarding:check-sla-warning    → fires job 4
 *   php artisan reports:daily-digest            → fires job 5 (uses existing admin/sale users)
 *   php artisan reports:weekly-trainer-report   → fires job 6 (uses existing admin users)
 *
 * Then process queued jobs:
 *   php artisan queue:work --queue=high,default,reports --stop-when-empty
 */
class HorizonJobTestSeeder extends Seeder
{
    // Static IDs — safe to re-run; updateOrCreate resets time-sensitive fields each run
    const APPT_REMINDER_24H_ID    = 'eeeeeeee-0000-0000-0000-000000000001';
    const APPT_REMINDER_1H_ID     = 'eeeeeeee-0000-0000-0000-000000000002';
    const APPT_NO_SHOW_ID         = 'eeeeeeee-0000-0000-0000-000000000003';
    const APPT_SLA_BASE_ID        = 'eeeeeeee-0000-0000-0000-000000000004';
    const ONBOARDING_SLA_ID       = 'ffffffff-0000-0000-0000-000000000001';

    public function run(): void
    {
        $trainerId = DemoUserSeeder::TRAINER_USER_ID;
        $saleId    = DemoUserSeeder::SALE_USER_IDS[0];
        $clientId  = ClientSeeder::CLIENT_ALPHA_ID;

        // ── Job 1: SendAppointmentReminder('24h') ────────────────────────────
        // Command window: scheduled_at between now+23h45m and now+24h15m
        $at24h = now()->addHours(24);
        Appointment::updateOrCreate(
            ['id' => self::APPT_REMINDER_24H_ID],
            [
                'appointment_code'   => 'TEST-REMINDER-24H',
                'title'              => '[TEST] Appointment in 24 hours',
                'appointment_type'   => 'training',
                'location_type'      => 'online',
                'status'             => 'pending',
                'trainer_id'         => $trainerId,
                'client_id'          => $clientId,
                'creator_id'         => $saleId,
                'scheduled_date'     => $at24h->toDateString(),
                'scheduled_start_time' => $at24h->format('H:i:s'),
                'scheduled_end_time'   => $at24h->addHour()->format('H:i:s'),
                'reminder_24h_sent_at' => null,
                'reminder_1h_sent_at'  => null,
                'no_show_notified_at'  => null,
                'is_onboarding_triggered' => false,
                'is_continued_session'    => false,
            ]
        );

        // ── Job 2: SendAppointmentReminder('1h') ─────────────────────────────
        // Command window: scheduled_at between now+50m and now+70m
        $at1h = now()->addHour();
        Appointment::updateOrCreate(
            ['id' => self::APPT_REMINDER_1H_ID],
            [
                'appointment_code'   => 'TEST-REMINDER-1H',
                'title'              => '[TEST] Appointment in 1 hour',
                'appointment_type'   => 'training',
                'location_type'      => 'online',
                'status'             => 'pending',
                'trainer_id'         => $trainerId,
                'client_id'          => $clientId,
                'creator_id'         => $saleId,
                'scheduled_date'     => $at1h->toDateString(),
                'scheduled_start_time' => $at1h->format('H:i:s'),
                'scheduled_end_time'   => $at1h->addHour()->format('H:i:s'),
                'reminder_24h_sent_at' => null,
                'reminder_1h_sent_at'  => null,
                'no_show_notified_at'  => null,
                'is_onboarding_triggered' => false,
                'is_continued_session'    => false,
            ]
        );

        // ── Job 3: NotifyAppointmentNoShow ───────────────────────────────────
        // Command checks: status=pending, scheduled_date=today, start_time+30m < now
        $noShowAt = now()->subMinutes(35);
        Appointment::updateOrCreate(
            ['id' => self::APPT_NO_SHOW_ID],
            [
                'appointment_code'   => 'TEST-NO-SHOW',
                'title'              => '[TEST] Overdue appointment (no-show)',
                'appointment_type'   => 'training',
                'location_type'      => 'online',
                'status'             => 'pending',
                'trainer_id'         => $trainerId,
                'client_id'          => $clientId,
                'creator_id'         => $saleId,
                'scheduled_date'     => $noShowAt->toDateString(),
                'scheduled_start_time' => $noShowAt->format('H:i:s'),
                'scheduled_end_time'   => $noShowAt->addHour()->format('H:i:s'),
                'reminder_24h_sent_at' => null,
                'reminder_1h_sent_at'  => null,
                'no_show_notified_at'  => null,
                'is_onboarding_triggered' => false,
                'is_continued_session'    => false,
            ]
        );

        // ── Job 4: SendOnboardingSlaWarning ──────────────────────────────────
        // Requires: due_date = today+3d, sla_warning_sent_at IS NULL, status not completed/cancelled
        // First seed the parent appointment (training, done — onboarding spawned from this)
        Appointment::updateOrCreate(
            ['id' => self::APPT_SLA_BASE_ID],
            [
                'appointment_code'   => 'TEST-SLA-BASE',
                'title'              => '[TEST] Base training for SLA onboarding',
                'appointment_type'   => 'training',
                'location_type'      => 'online',
                'status'             => 'done',
                'trainer_id'         => $trainerId,
                'client_id'          => $clientId,
                'creator_id'         => $saleId,
                'scheduled_date'     => now()->subDays(7)->toDateString(),
                'scheduled_start_time' => '09:00:00',
                'scheduled_end_time'   => '10:00:00',
                'is_onboarding_triggered' => true,
                'is_continued_session'    => false,
            ]
        );

        OnboardingRequest::updateOrCreate(
            ['id' => self::ONBOARDING_SLA_ID],
            [
                'request_code'       => 'ONB-TEST-SLA-001',
                'appointment_id'     => self::APPT_SLA_BASE_ID,
                'client_id'          => $clientId,
                'trainer_id'         => $trainerId,
                'status'             => 'in_progress',
                'progress_percentage' => 45.00,
                'due_date'           => now()->addDays(3)->toDateString(),
                'sla_breached_at'    => null,
                'sla_warning_sent_at' => null,
            ]
        );

        $this->command->newLine();
        $this->command->info('Horizon job test data seeded. Trigger each command to dispatch jobs:');
        $this->command->table(
            ['Command', 'Dispatches Job', 'Target'],
            [
                ['appointment:send-reminders',   'SendAppointmentReminder(24h)', 'TEST-REMINDER-24H → trainer notified'],
                ['appointment:send-reminders',   'SendAppointmentReminder(1h)',  'TEST-REMINDER-1H  → trainer notified'],
                ['appointment:check-no-show',    'NotifyAppointmentNoShow',      'TEST-NO-SHOW      → all admins notified'],
                ['onboarding:check-sla-warning', 'SendOnboardingSlaWarning',     'ONB-TEST-SLA-001  → trainer + sale notified'],
                ['reports:daily-digest',         'SendDailyDigest',              'All admin + sale users'],
                ['reports:weekly-trainer-report','SendWeeklyTrainerReport',      'All admin users'],
            ]
        );
        $this->command->newLine();
        $this->command->line('Process dispatched jobs:');
        $this->command->line('  php artisan queue:work --queue=high,default,reports --stop-when-empty');
    }
}
