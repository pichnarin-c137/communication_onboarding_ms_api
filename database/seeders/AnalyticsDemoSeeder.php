<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AnalyticsDemoSeeder
 * -------------------
 * Seeds a realistic dataset so the /analytics dashboards render meaningful data
 * for the EXISTING admin, sale, and trainer login accounts.
 *
 * Hard rules honoured:
 *  - NEVER creates auth users / roles / credentials. Reuses the current users.
 *  - Every demo appointment has creator_id = a sale, trainer_id = a trainer that
 *    belongs to that sale's roster, so all three roles' scoping returns rows
 *    (see AnalyticsScope::applyAppointmentScope / applyOnboardingScope).
 *  - Idempotent: on each run it wipes ONLY the rows it previously created
 *    (tagged via 'ADX-' codes / emails / group names) and never touches users.
 *  - Local/dev only.
 *
 * Run:  docker compose exec app php artisan db:seed --class=AnalyticsDemoSeeder
 *       docker compose exec app php artisan cache:clear   # bust 5-min analytics cache
 */
class AnalyticsDemoSeeder extends Seeder
{
    private const TAG = 'ADX-';                 // code prefix marker for re-runnable wipes
    private const RESPONDENT_EMAIL = 'adx+';    // feedback_respondents email marker
    private const GROUP_NAME = 'ADX ';          // telegram_groups group_name marker

    private CarbonImmutable $now;

    /** Buffered rows, flushed in FK-safe order. */
    private array $buf = [];

    public function run(): void
    {
        $this->now = CarbonImmutable::now();

        // ---- Resolve EXISTING users (no creation) ----------------------------
        $admin    = $this->roleUsers('admin')->first();
        $sales    = $this->roleUsers('sale')->values();
        $trainers = $this->roleUsers('trainer')->values();

        if (! $admin || $sales->isEmpty() || $trainers->isEmpty()) {
            $this->command->error('AnalyticsDemoSeeder: need at least 1 admin, 1 sale and 1 trainer user. Run DemoUserSeeder first.');

            return;
        }

        $primarySale    = $sales->firstWhere('id', 'bbbbbbbb-0000-0000-0000-000000000001') ?? $sales->first();
        $primaryTrainer = $trainers->firstWhere('id', 'bbbbbbbb-0000-0000-0000-000000000002') ?? $trainers->first();

        $this->command->info('AnalyticsDemoSeeder: wiping previous demo rows…');
        $this->wipePrevious();

        // ---- Roster: every sale → ≥2 trainers; every trainer used -----------
        $roster = $this->ensureRosters($sales, $trainers);   // [saleId => [trainerId,...]]

        // ---- Clients: reuse existing + a few tagged demo clients ------------
        $clients = $this->buildClientPool($sales);           // collection of {id, company_name, sale_id}

        $this->command->info('AnalyticsDemoSeeder: generating appointments…');
        $appointments = $this->generateAppointments($roster, $clients, $primarySale->id, $primaryTrainer->id);

        $this->command->info('AnalyticsDemoSeeder: generating onboardings + lifecycle…');
        $onboardings = $this->generateOnboardings($appointments, $primarySale->id, $primaryTrainer->id);

        $this->command->info('AnalyticsDemoSeeder: generating feedback…');
        $this->generateFeedback($appointments, $onboardings, $clients, $primarySale->id, $primaryTrainer->id);

        $this->command->info('AnalyticsDemoSeeder: generating telegram + lessons…');
        $this->generateEngagement($onboardings, $clients);

        // ---- Flush all buffered rows in FK-safe order -----------------------
        $this->flush();

        $this->report();
    }

    // =========================================================================
    // Wipe (re-runnable)
    // =========================================================================

    private function wipePrevious(): void
    {
        $aptIds = DB::table('appointments')->where('appointment_code', 'like', self::TAG.'%')->pluck('id')->all();
        $onbIds = DB::table('onboarding_requests')->where('request_code', 'like', self::TAG.'%')->pluck('id')->all();
        $clientIds = DB::table('clients')->where('code', 'like', self::TAG.'%')->pluck('id')->all();
        $groupIds = DB::table('telegram_groups')->where('group_name', 'like', self::GROUP_NAME.'%')->pluck('id')->all();

        // children of onboardings
        if ($onbIds) {
            DB::table('onboarding_status_history')->whereIn('onboarding_id', $onbIds)->delete();
            DB::table('onboarding_lessons')->whereIn('onboarding_id', $onbIds)->delete();
            DB::table('onboarding_client_feedbacks')->whereIn('onboarding_id', $onbIds)->delete();
            DB::table('onboarding_trainer_assignments')->whereIn('onboarding_id', $onbIds)->delete();
        }
        // children of appointments
        if ($aptIds) {
            DB::table('appointment_feedback')->whereIn('appointment_id', $aptIds)->delete();
            DB::table('appointment_feedback_tokens')->whereIn('appointment_id', $aptIds)->delete();
        }
        // telegram messages belong to demo groups
        if ($groupIds) {
            DB::table('telegram_messages')->whereIn('telegram_group_id', $groupIds)->delete();
        }
        // null out appointment → onboarding back-reference before deleting onboardings
        if ($aptIds) {
            DB::table('appointments')->whereIn('id', $aptIds)->update(['related_onboarding_id' => null]);
        }
        if ($onbIds) {
            DB::table('onboarding_requests')->whereIn('id', $onbIds)->delete();
        }
        if ($aptIds) {
            DB::table('appointments')->whereIn('id', $aptIds)->delete();
        }
        if ($groupIds) {
            DB::table('telegram_groups')->whereIn('id', $groupIds)->delete();
        }
        DB::table('feedback_respondents')->where('email', 'like', self::RESPONDENT_EMAIL.'%')->delete();
        if ($clientIds) {
            DB::table('clients')->whereIn('id', $clientIds)->delete();
        }
    }

    // =========================================================================
    // Users / roster / clients
    // =========================================================================

    private function roleUsers(string $role)
    {
        return DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->whereNull('u.deleted_at')
            ->where('r.role', $role)
            ->orderBy('u.id')
            ->get(['u.id', 'u.first_name', 'u.last_name']);
    }

    /**
     * Ensure each sale has at least 2 trainers and every trainer is on ≥1 roster.
     * Preserves existing rows; inserts only what is missing.
     *
     * @return array<string, list<string>>  saleId => trainerIds
     */
    private function ensureRosters($sales, $trainers): array
    {
        $existing = DB::table('sale_trainer_assignments')
            ->whereNull('deleted_at')
            ->get(['sale_user_id', 'trainer_user_id']);

        $map = [];
        foreach ($sales as $s) {
            $map[$s->id] = [];
        }
        foreach ($existing as $row) {
            $map[$row->sale_user_id][] = $row->trainer_user_id;
        }

        $saleIds = $sales->pluck('id')->all();
        $trainerIds = $trainers->pluck('id')->all();
        $saleCount = count($saleIds);

        // Spread every trainer across sales round-robin so all trainers are covered.
        foreach ($trainerIds as $i => $tid) {
            $sid = $saleIds[$i % $saleCount];
            if (! in_array($tid, $map[$sid], true)) {
                $map[$sid][] = $tid;
            }
        }
        // Guarantee a minimum of 2 trainers per sale.
        foreach ($saleIds as $sid) {
            $j = 0;
            while (count($map[$sid]) < 2 && $j < count($trainerIds)) {
                $tid = $trainerIds[$j++];
                if (! in_array($tid, $map[$sid], true)) {
                    $map[$sid][] = $tid;
                }
            }
        }

        // Persist any (sale,trainer) pair not already present.
        $present = [];
        foreach ($existing as $row) {
            $present[$row->sale_user_id.'|'.$row->trainer_user_id] = true;
        }
        $insert = [];
        foreach ($map as $sid => $tids) {
            foreach ($tids as $tid) {
                if (! isset($present[$sid.'|'.$tid])) {
                    $insert[] = [
                        'id' => (string) Str::uuid(),
                        'sale_user_id' => $sid,
                        'trainer_user_id' => $tid,
                        'assigned_at' => $this->now,
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ];
                }
            }
        }
        if ($insert) {
            DB::table('sale_trainer_assignments')->insert($insert);
        }

        return $map;
    }

    /**
     * Reuse existing clients (so company names are real) + add a few tagged demo
     * clients for variety. Returns collection of {id, company_name, sale_id}.
     */
    private function buildClientPool($sales)
    {
        $pool = collect();

        DB::table('clients')->whereNull('deleted_at')->get(['id', 'company_name', 'assigned_sale_id'])
            ->each(function ($c) use ($pool) {
                $pool->push((object) ['id' => $c->id, 'company_name' => $c->company_name, 'sale_id' => $c->assigned_sale_id]);
            });

        $extraNames = [
            'Lambda Retail Co', 'Mu Manufacturing', 'Nu Hospitality', 'Xi Agritech',
            'Omicron Finance', 'Pi Healthcare', 'Rho Education', 'Sigma Telecom',
        ];
        $saleIds = $sales->pluck('id')->all();
        foreach ($extraNames as $i => $name) {
            $id = (string) Str::uuid();
            $saleId = $saleIds[$i % count($saleIds)];
            $this->buf['clients'][] = [
                'id' => $id,
                'code' => self::TAG.Str::upper(Str::random(6)),
                'company_code' => 'REG-KH-'.str_pad((string) (9000 + $i), 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'company_name' => $name,
                'phone_number' => '+8551'.str_pad((string) rand(0, 9999999), 7, '0', STR_PAD_LEFT),
                'email' => 'info'.$i.'@'.Str::slug($name).'.test',
                'headquarter_address' => 'Phnom Penh, Cambodia',
                'headquarter_latitude' => 11.50 + (rand(0, 100) / 1000),
                'headquarter_longitude' => 104.85 + (rand(0, 100) / 1000),
                'is_active' => true,
                'geofence_radius' => 200,
                'assigned_sale_id' => $saleId,
                'created_at' => $this->now->subDays(rand(60, 180)),
                'updated_at' => $this->now,
            ];
            $pool->push((object) ['id' => $id, 'company_name' => $name, 'sale_id' => $saleId]);
        }

        return $pool;
    }

    // =========================================================================
    // Appointments
    // =========================================================================

    /**
     * @return array<int, object>  generated appointment descriptors
     */
    private function generateAppointments(array $roster, $clients, string $primarySaleId, string $primaryTrainerId): array
    {
        $appts = [];
        $base = 90;

        for ($i = 0; $i < $base; $i++) {
            // Heavily weight the primary sale so logging in as it is rich.
            $saleId = (rand(1, 100) <= 45) ? $primarySaleId : $this->pick(array_keys($roster));
            $trainerId = $this->pickTrainer($roster, $saleId, $primarySaleId, $primaryTrainerId);
            $client = $this->pickClient($clients, $saleId);

            $type = (rand(1, 100) <= 40) ? 'demo' : 'training';
            $appts[] = $this->makeAppointment($saleId, $trainerId, $client, $type, $this->weightedScheduledDate());
        }

        // ---- Demo → training conversions (drives demo_to_training_conversion) ----
        $doneDemoByClient = [];
        foreach ($appts as $a) {
            if ($a->appointment_type === 'demo' && $a->status === 'done') {
                $doneDemoByClient[$a->client_id] ??= $a; // first done demo per client
            }
        }
        foreach ($doneDemoByClient as $demo) {
            if (rand(1, 100) > 70) {
                continue; // ~70% convert
            }
            $client = (object) ['id' => $demo->client_id, 'company_name' => $demo->client_name, 'sale_id' => $demo->creator_id];
            $createdAt = CarbonImmutable::parse($demo->scheduled_date)->addDays(rand(2, 20))->setTime(rand(8, 17), rand(0, 59));
            if ($createdAt->greaterThan($this->now)) {
                continue;
            }
            $schedDate = $createdAt->addDays(rand(1, 7));
            $training = $this->makeAppointment(
                $demo->creator_id,
                $demo->trainer_id,
                $client,
                'training',
                $schedDate->lessThan($this->now) ? $schedDate : $createdAt,
                $createdAt,
            );
            $appts[] = $training;
        }

        return $appts;
    }

    private function makeAppointment(string $saleId, string $trainerId, object $client, string $type, CarbonImmutable $schedDate, ?CarbonImmutable $createdAt = null): object
    {
        // Avoid Sundays so the heatmap stays within Mon–Sat.
        if ((int) $schedDate->isoWeekday() === 7) {
            $schedDate = $schedDate->subDay();
        }
        $startHour = rand(8, 17);
        $startTime = sprintf('%02d:00:00', $startHour);
        $endTime = sprintf('%02d:00:00', min($startHour + rand(1, 2), 19));

        $createdAt ??= $schedDate->subDays(rand(1, 10))->setTime(rand(8, 18), rand(0, 59));
        $status = $this->weightedStatus();
        $location = $this->pick(['online', 'physical', 'hybrid']);

        $id = (string) Str::uuid();
        $row = [
            'id' => $id,
            'title' => ($type === 'training' ? 'Training — ' : 'Demo — ').$client->company_name,
            'appointment_code' => self::TAG.Str::upper(Str::random(8)),
            'appointment_type' => $type,
            'location_type' => $location,
            'status' => $status,
            'trainer_id' => $trainerId,
            'client_id' => $client->id,
            'creator_id' => $saleId,
            'scheduled_date' => $schedDate->toDateString(),
            'scheduled_start_time' => $startTime,
            'scheduled_end_time' => $endTime,
            'student_count' => 0,
            'is_onboarding_triggered' => false,
            'is_continued_session' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        $schedDt = CarbonImmutable::parse($schedDate->toDateString().' '.$startTime);

        if ($status === 'done') {
            $onTime = rand(1, 100) <= 85;
            $startedAt = $onTime ? $schedDt->addMinutes(rand(0, 14)) : $schedDt->addMinutes(rand(20, 60));
            $row['actual_start_time'] = $startedAt;
            $row['actual_end_time'] = $startedAt->addMinutes(rand(45, 90));
            $row['student_count'] = (rand(1, 100) <= 5) ? 0 : rand(1, 12); // ~5% no-show
        } elseif ($status === 'cancelled') {
            $row['cancelled_at'] = $schedDt->subDays(rand(0, 2));
            $row['cancellation_reason'] = $this->pick(['Client unavailable', 'Rescheduling requested', 'Internal conflict']);
            $row['cancelled_by_user_id'] = $saleId;
        } elseif ($status === 'rescheduled') {
            $row['reschedule_at'] = $schedDt->subDays(rand(0, 2));
            $row['reschedule_reason'] = $this->pick(['Client requested new date', 'Trainer conflict']);
            $row['reschedule_to_date'] = $schedDate->addDays(rand(2, 10))->toDateString();
            $row['reschedule_to_start_time'] = $startTime;
            $row['reschedule_to_end_time'] = $endTime;
        }

        $this->buf['appointments'][] = $row;

        return (object) ($row + ['client_name' => $client->company_name]);
    }

    // =========================================================================
    // Onboardings + status history
    // =========================================================================

    /**
     * @return array<int, object>
     */
    private function generateOnboardings(array $appointments, string $primarySaleId, string $primaryTrainerId): array
    {
        // Candidate source appointments = done trainings (creator is always a sale).
        $sources = array_values(array_filter($appointments, fn ($a) => $a->appointment_type === 'training' && $a->status === 'done'));

        $target = 60;
        // If not enough done trainings, synthesise extra done-training sources.
        while (count($sources) < $target) {
            $proto = $sources[array_rand($sources)] ?? null;
            $saleId = $proto->creator_id ?? $primarySaleId;
            $trainerId = $proto->trainer_id ?? $primaryTrainerId;
            $client = (object) [
                'id' => $proto->client_id,
                'company_name' => $proto->client_name,
                'sale_id' => $saleId,
            ];
            $sources[] = $this->makeAppointment($saleId, $trainerId, $client, 'training', $this->weightedScheduledDate());
            $sources = array_values(array_filter($sources, fn ($a) => $a->appointment_type === 'training' && $a->status === 'done'));
        }

        shuffle($sources);
        $sources = array_slice($sources, 0, $target);

        $onboardings = [];
        $i = 0;
        foreach ($sources as $src) {
            $status = $this->weightedOnboardingStatus($i, $target);
            $completed = $status === 'completed';

            // Completed ones are older (room for the lifecycle to land before now);
            // active ones are recent so both period halves are populated.
            $createdAt = $completed
                ? $this->now->subDays(rand(16, 85))->setTime(rand(8, 17), rand(0, 59))
                : $this->now->subDays(rand(0, 40))->setTime(rand(8, 17), rand(0, 59));

            $everHeld = $status === 'on_hold' || rand(1, 100) <= 25;
            $everRevised = rand(1, 100) <= 17;
            $cycle = (rand(1, 100) <= 10) ? rand(2, 3) : 1;

            $onbId = (string) Str::uuid();
            $row = [
                'id' => $onbId,
                'request_code' => self::TAG.strtoupper(Str::random(6)),
                'appointment_id' => $src->id,
                'client_id' => $src->client_id,
                'trainer_id' => $src->trainer_id,
                'status' => $status,
                'progress_percentage' => match ($status) {
                    'completed' => 100,
                    'in_progress' => rand(20, 90),
                    'on_hold' => rand(15, 70),
                    'revision_requested' => rand(60, 95),
                    default => rand(0, 20),
                },
                'hold_count' => $everHeld ? rand(1, 2) : 0,
                'cycle_number' => $cycle,
                'created_at' => $createdAt,
                'updated_at' => $this->now,
            ];
            if ($everHeld) {
                $row['hold_reason'] = $this->pick(['Awaiting client data', 'Pending hardware', 'Holiday closure']);
                $row['hold_started_at'] = $createdAt->addDays(rand(2, 10));
            }
            if ($everRevised) {
                $row['revision_note'] = $this->pick(['Client requested policy changes', 'Re-do system mapping', 'Adjust lesson plan']);
                $row['revision_requested_at'] = $createdAt->addDays(rand(3, 12));
            }

            // ---- Lifecycle timeline (drives "avg time in stage") ----
            $completedAt = $this->buildStatusHistory($onbId, $createdAt, $status, $everHeld, $everRevised);
            if ($completed) {
                $row['completed_at'] = $completedAt;
            }

            $this->buf['onboarding_requests'][] = $row;

            // current trainer assignment (attribution + alert names)
            $this->buf['onboarding_trainer_assignments'][] = [
                'id' => (string) Str::uuid(),
                'onboarding_id' => $onbId,
                'trainer_id' => $src->trainer_id,
                'assigned_at' => $createdAt,
                'is_current' => true,
                'created_at' => $createdAt,
                'updated_at' => $this->now,
            ];
            // reassigned ones get a prior (replaced) assignment
            if ($cycle > 1) {
                $prevTrainer = $primaryTrainerId === $src->trainer_id ? $src->trainer_id : $primaryTrainerId;
                $this->buf['onboarding_trainer_assignments'][] = [
                    'id' => (string) Str::uuid(),
                    'onboarding_id' => $onbId,
                    'trainer_id' => $prevTrainer,
                    'assigned_at' => $createdAt->subDays(rand(10, 30)),
                    'is_current' => false,
                    'replaced_at' => $createdAt,
                    'created_at' => $createdAt->subDays(rand(10, 30)),
                    'updated_at' => $this->now,
                ];
            }

            $onboardings[] = (object) ($row + [
                'completed_at' => $completedAt,
                'is_completed' => $completed,
                'creator_id' => $src->creator_id,
                'client_name' => $src->client_name,
            ]);
            $i++;
        }

        return $onboardings;
    }

    /**
     * Walks pending → in_progress → (revision) → (on_hold) → (completed) with
     * gaps tuned to: pending ~12h, in_progress ~280h, on_hold ~48h, revision ~18h.
     * Returns the completed_at timestamp (or last change time for non-completed).
     */
    private function buildStatusHistory(string $onbId, CarbonImmutable $createdAt, string $status, bool $everHeld, bool $everRevised): CarbonImmutable
    {
        $events = [];
        $events[] = ['from' => null, 'to' => 'pending', 'at' => $createdAt];

        $t = $createdAt->addHours($this->jitter(12));        // pending → in_progress
        $events[] = ['from' => 'pending', 'to' => 'in_progress', 'at' => $t];

        if ($everRevised) {
            $t = $t->addHours($this->jitter(140));            // partial in_progress
            $events[] = ['from' => 'in_progress', 'to' => 'revision_requested', 'at' => $t];
            $t = $t->addHours($this->jitter(18));             // revision ~18h
            $events[] = ['from' => 'revision_requested', 'to' => 'in_progress', 'at' => $t];
        }

        $t = $t->addHours($this->jitter(140));                // bulk of in_progress (≈280h total)

        if ($everHeld) {
            $events[] = ['from' => 'in_progress', 'to' => 'on_hold', 'at' => $t];
            $t = $t->addHours($this->jitter(48));             // on_hold ~48h
            $events[] = ['from' => 'on_hold', 'to' => 'in_progress', 'at' => $t];
            $t = $t->addHours($this->jitter(60));
        }

        $last = $t;
        if ($status === 'completed') {
            $events[] = ['from' => 'in_progress', 'to' => 'completed', 'at' => $t];
            $last = $t;
        } elseif ($status === 'cancelled') {
            $events[] = ['from' => 'in_progress', 'to' => 'cancelled', 'at' => $t];
            $last = $t;
        } elseif ($status === 'on_hold') {
            // leave it sitting on_hold as the terminal state
            $events[] = ['from' => 'in_progress', 'to' => 'on_hold', 'at' => $t];
            $last = $t;
        } elseif ($status === 'revision_requested') {
            $events[] = ['from' => 'in_progress', 'to' => 'revision_requested', 'at' => $t];
            $last = $t;
        }
        // 'in_progress' terminal: timeline already ends on in_progress.

        // Clamp to now and persist.
        foreach ($events as $e) {
            $at = $e['at']->greaterThan($this->now) ? $this->now : $e['at'];
            $this->buf['onboarding_status_history'][] = [
                'id' => (string) Str::uuid(),
                'onboarding_id' => $onbId,
                'from_status' => $e['from'],
                'to_status' => $e['to'],
                'changed_at' => $at,
                'changed_by_user_id' => null,
                'reason' => null,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        return $last->greaterThan($this->now) ? $this->now : $last;
    }

    // =========================================================================
    // Feedback (both tables) + low-rating alerts
    // =========================================================================

    private function generateFeedback(array $appointments, array $onboardings, $clients, string $primarySaleId, string $primaryTrainerId): void
    {
        // ---- Onboarding client feedback: ~70% of completed (unique per onboarding) ----
        $completed = array_values(array_filter($onboardings, fn ($o) => $o->is_completed));
        $lowOnbBudget = 3;
        foreach ($completed as $o) {
            if (rand(1, 100) > 70) {
                continue;
            }
            $forceLow = $lowOnbBudget > 0
                && $o->trainer_id === $primaryTrainerId
                && $o->creator_id === $primarySaleId;

            if ($forceLow) {
                $rating = rand(1, 2);
                $submittedAt = $this->now->subDays(rand(1, 5))->setTime(rand(9, 17), rand(0, 59));
                $comment = $this->complaint();
                $lowOnbBudget--;
            } else {
                $rating = $this->skewRating();
                $base = $o->completed_at ?? $this->now->subDays(rand(1, 20));
                $submittedAt = $base->addDays(rand(0, 3));
                if ($submittedAt->greaterThan($this->now)) {
                    $submittedAt = $this->now->subDays(rand(0, 2));
                }
                $comment = $this->praise();
            }

            $this->buf['onboarding_client_feedbacks'][] = [
                'id' => (string) Str::uuid(),
                'onboarding_id' => $o->id,
                'rating' => $rating,
                'comment' => $comment,
                'submitted_via' => $this->pick(['manual', 'email']),
                'submitted_by_user_id' => null,
                'submitted_at' => $submittedAt,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ];
        }

        // ---- Appointment feedback: ~40% of done appointments (+ respondent + token) ----
        $doneAppts = array_values(array_filter($appointments, fn ($a) => $a->status === 'done'));
        $clientById = $clients->keyBy('id');
        $lowAptBudget = 3;

        foreach ($doneAppts as $a) {
            if (rand(1, 100) > 40) {
                continue;
            }
            $forceLow = $lowAptBudget > 0
                && $a->trainer_id === $primaryTrainerId
                && $a->creator_id === $primarySaleId;

            if ($forceLow) {
                $rating = rand(1, 2);
                $submittedAt = $this->now->subDays(rand(1, 5))->setTime(rand(9, 17), rand(0, 59));
                $comment = $this->complaint();
                $lowAptBudget--;
            } else {
                $rating = $this->skewRating();
                $schedDt = CarbonImmutable::parse($a->scheduled_date.' '.$a->scheduled_start_time);
                $submittedAt = $schedDt->addDays(rand(0, 4));
                if ($submittedAt->greaterThan($this->now)) {
                    $submittedAt = $this->now->subDays(rand(0, 3));
                }
                $comment = $rating <= 3 ? $this->complaint() : $this->praise();
            }

            $respondentId = (string) Str::uuid();
            $clientName = $clientById->get($a->client_id)->company_name ?? 'Client Contact';
            $this->buf['feedback_respondents'][] = [
                'id' => $respondentId,
                'client_id' => $a->client_id,
                'name' => 'Contact '.Str::upper(Str::random(4)),
                'email' => self::RESPONDENT_EMAIL.Str::lower(Str::random(6)).'@demo.test',
                'phone_number' => '+8551'.str_pad((string) rand(0, 9999999), 7, '0', STR_PAD_LEFT),
                'position' => $this->pick(['HR Manager', 'Operations Lead', 'Owner', 'Admin']),
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ];
            $tokenId = (string) Str::uuid();
            $this->buf['appointment_feedback_tokens'][] = [
                'id' => $tokenId,
                'appointment_id' => $a->id,
                'token' => Str::random(40),
                'expires_at' => $submittedAt->addDays(7),
                'is_active' => false,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ];
            $this->buf['appointment_feedback'][] = [
                'id' => (string) Str::uuid(),
                'appointment_id' => $a->id,
                'token_id' => $tokenId,
                'respondent_id' => $respondentId,
                'rating' => $rating,
                'comment' => $comment,
                'submitted_at' => $submittedAt,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ];
        }
    }

    // =========================================================================
    // Engagement: telegram groups/messages + onboarding lessons
    // =========================================================================

    private function generateEngagement(array $onboardings, $clients): void
    {
        // ---- Telegram groups (current counts, not date-filtered) ----
        $statuses = ['connected', 'connected', 'connected', 'connected', 'reconnected', 'removed', 'removed'];
        $groupIds = [];
        $clientList = $clients->values();
        foreach ($statuses as $idx => $st) {
            $client = $clientList[$idx % $clientList->count()];
            $gid = (string) Str::uuid();
            $groupIds[] = $gid;
            $this->buf['telegram_groups'][] = [
                'id' => $gid,
                'client_id' => $client->id,
                'chat_id' => 'adx_'.rand(100000000, 999999999),
                'group_name' => self::GROUP_NAME.$client->company_name,
                'bot_status' => $st,
                'language' => $this->pick(['en', 'km']),
                'connected_by' => null,
                'connected_at' => $this->now->subDays(rand(20, 80)),
                'reconnected_at' => $st === 'reconnected' ? $this->now->subDays(rand(1, 10)) : null,
                'disconnected_at' => $st === 'removed' ? $this->now->subDays(rand(1, 30)) : null,
                'created_at' => $this->now->subDays(rand(20, 80)),
                'updated_at' => $this->now,
            ];
        }

        // ---- Telegram messages (~400) ----
        $types = ['appointment_reminder', 'onboarding_update', 'lesson', 'test', 'feedback_request'];
        for ($i = 0; $i < 400; $i++) {
            $sentAt = $this->weightedScheduledDate()->setTime(rand(8, 19), rand(0, 59));
            $isFailed = rand(1, 100) <= 5;
            $this->buf['telegram_messages'][] = [
                'id' => (string) Str::uuid(),
                'telegram_group_id' => $groupIds[array_rand($groupIds)],
                'message_type' => $this->pick($types),
                'message_body' => 'Automated notification #'.$i,
                'language' => $this->pick(['en', 'km']),
                'status' => $isFailed ? 'failed' : $this->pick(['sent', 'delivered', 'delivered']),
                'error_message' => $isFailed ? 'Telegram API timeout' : null,
                'sent_at' => $isFailed ? null : $sentAt,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ];
        }

        // ---- Onboarding lessons (~120) linked to seeded onboardings ----
        $count = 0;
        $targetLessons = 120;
        $onbList = $onboardings;
        while ($count < $targetLessons) {
            foreach ($onbList as $o) {
                if ($count >= $targetLessons) {
                    break;
                }
                // 1–3 lessons per onboarding across passes
                $sentAt = ($o->completed_at ?? $this->now->subDays(rand(1, 40)))
                    ->subDays(rand(0, 20))->setTime(rand(8, 18), rand(0, 59));
                if ($sentAt->greaterThan($this->now)) {
                    $sentAt = $this->now->subDays(rand(1, 5));
                }
                if ($sentAt->lessThan($o->created_at)) {
                    $sentAt = $o->created_at->addDays(rand(1, 5));
                    if ($sentAt->greaterThan($this->now)) {
                        $sentAt = $this->now;
                    }
                }
                $this->buf['onboarding_lessons'][] = [
                    'id' => (string) Str::uuid(),
                    'onboarding_id' => $o->id,
                    'path' => rand(1, 3),
                    'is_sent' => true,
                    'sent_at' => $sentAt,
                    'sent_by_user_id' => $o->trainer_id,
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                ];
                $count++;
            }
        }
    }

    // =========================================================================
    // Flush + helpers
    // =========================================================================

    private function flush(): void
    {
        // FK-safe insert order.
        $order = [
            'clients',
            'appointments',
            'telegram_groups',
            'onboarding_requests',
            'onboarding_trainer_assignments',
            'onboarding_status_history',
            'onboarding_lessons',
            'onboarding_client_feedbacks',
            'feedback_respondents',
            'appointment_feedback_tokens',
            'appointment_feedback',
            'telegram_messages',
        ];

        foreach ($order as $table) {
            $rows = $this->buf[$table] ?? [];
            if (empty($rows)) {
                continue;
            }

            // Batch insert requires every row to share the same columns; union all
            // keys for the table and back-fill the missing ones with null.
            $allKeys = [];
            foreach ($rows as $row) {
                $allKeys += $row;
            }
            $template = array_fill_keys(array_keys($allKeys), null);

            $rows = $this->normalize($rows, $template);
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }

    /** Back-fill missing columns and convert CarbonImmutable values to DB strings. */
    private function normalize(array $rows, array $template): array
    {
        foreach ($rows as &$row) {
            $row = array_merge($template, $row);
            foreach ($row as $k => $v) {
                if ($v instanceof CarbonImmutable) {
                    $row[$k] = $v->toDateTimeString();
                }
            }
        }

        return $rows;
    }

    private function pickTrainer(array $roster, string $saleId, string $primarySaleId, string $primaryTrainerId): string
    {
        $list = $roster[$saleId] ?? [];
        if (empty($list)) {
            return $primaryTrainerId;
        }
        // Weight the primary trainer when on the primary sale's roster.
        if ($saleId === $primarySaleId && in_array($primaryTrainerId, $list, true) && rand(1, 100) <= 50) {
            return $primaryTrainerId;
        }

        return $list[array_rand($list)];
    }

    private function pickClient($clients, string $saleId): object
    {
        $own = $clients->where('sale_id', $saleId)->values();
        if ($own->isNotEmpty() && rand(1, 100) <= 70) {
            return $own[array_rand($own->all())];
        }

        return $clients[array_rand($clients->all())];
    }

    private function weightedScheduledDate(): CarbonImmutable
    {
        $r = rand(1, 100);
        if ($r <= 60) {
            $days = rand(0, 30);
        } elseif ($r <= 85) {
            $days = rand(31, 60);
        } else {
            $days = rand(61, 90);
        }

        return $this->now->subDays($days);
    }

    private function weightedStatus(): string
    {
        $r = rand(1, 100);

        return match (true) {
            $r <= 75 => 'done',
            $r <= 83 => 'cancelled',
            $r <= 90 => 'rescheduled',
            $r <= 96 => 'pending',
            $r <= 98 => 'in_progress',
            default  => 'leave_office',
        };
    }

    private function weightedOnboardingStatus(int $i, int $total): string
    {
        // Deterministic proportions: 60% completed, 20% in_progress, 10% on_hold, 10% cancelled.
        $pos = $i / max($total, 1);

        return match (true) {
            $pos < 0.60 => 'completed',
            $pos < 0.80 => 'in_progress',
            $pos < 0.90 => 'on_hold',
            default     => 'cancelled',
        };
    }

    private function skewRating(): int
    {
        $r = rand(1, 100);

        return match (true) {
            $r <= 45 => 5,
            $r <= 75 => 4,
            $r <= 90 => 3,
            $r <= 96 => 2,
            default  => 1,
        };
    }

    private function jitter(int $hours): int
    {
        return max(1, (int) round($hours * (0.8 + (rand(0, 40) / 100))));
    }

    private function pick(array $arr)
    {
        return $arr[array_rand($arr)];
    }

    private function praise(): string
    {
        return $this->pick([
            'Excellent trainer, very clear explanations.',
            'Smooth onboarding, the team was responsive.',
            'Great session, our staff learned a lot.',
            'Very professional and well organised.',
            'Helpful and patient throughout the process.',
        ]);
    }

    private function complaint(): string
    {
        return $this->pick([
            'Trainer arrived late and rushed the session.',
            'Onboarding took too long and communication was poor.',
            'The material was confusing and not well prepared.',
            'We expected more hands-on support, disappointed.',
            'Several issues were left unresolved after the session.',
        ]);
    }

    private function report(): void
    {
        $counts = [
            'clients (demo)' => count($this->buf['clients'] ?? []),
            'appointments' => count($this->buf['appointments'] ?? []),
            'onboardings' => count($this->buf['onboarding_requests'] ?? []),
            'status_history' => count($this->buf['onboarding_status_history'] ?? []),
            'onb_feedback' => count($this->buf['onboarding_client_feedbacks'] ?? []),
            'apt_feedback' => count($this->buf['appointment_feedback'] ?? []),
            'telegram_groups' => count($this->buf['telegram_groups'] ?? []),
            'telegram_messages' => count($this->buf['telegram_messages'] ?? []),
            'lessons' => count($this->buf['onboarding_lessons'] ?? []),
        ];
        $this->command->info('AnalyticsDemoSeeder complete:');
        foreach ($counts as $k => $v) {
            $this->command->line(sprintf('  %-22s %d', $k, $v));
        }
        $this->command->warn('Run `php artisan cache:clear` to bust the 5-min analytics cache.');
    }
}
