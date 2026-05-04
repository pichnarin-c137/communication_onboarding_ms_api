<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class AppointmentAnalyticsService
{
    public function listAnalytics(string $role, Collection $collection): array
    {
        $allStatuses = ['pending', 'leave_office', 'in_progress', 'done', 'cancelled', 'rescheduled'];
        $byStatus = array_merge(
            array_fill_keys($allStatuses, 0),
            $collection->countBy('status')->all()
        );

        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();

        $todayCount = $collection->filter(fn ($a) => $a->scheduled_date->toDateString() === $today)->count();
        $monthCount = $collection->filter(fn ($a) => $a->scheduled_date->toDateString() >= $monthStart)->count();
        $doneCount = $byStatus['done'];
        $cancelledCount = $byStatus['cancelled'];

        $completionRate = ($doneCount + $cancelledCount) > 0
            ? round($doneCount / ($doneCount + $cancelledCount) * 100, 1)
            : null;

        $healthCounts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($collection as $appt) {
            $healthCounts[$this->healthFlag($appt)['severity']]++;
        }

        $analytics = [
            'by_status' => $byStatus,
            'today' => $todayCount,
            'this_month' => $monthCount,
            'completion_rate' => $completionRate,
            'health_summary' => $healthCounts,
        ];

        if ($role === 'trainer') {
            $done = $collection->where('status', 'done');

            $analytics['total_students_trained'] = (int) $done->sum('student_count');

            $durations = $done
                ->filter(fn ($a) => $a->actual_start_time && $a->actual_end_time)
                ->map(fn ($a) => (int) Carbon::parse($a->actual_start_time)
                    ->diffInMinutes(Carbon::parse($a->actual_end_time)))
                ->values();

            $analytics['avg_session_duration_minutes'] = $durations->isNotEmpty()
                ? (int) round($durations->average())
                : null;

            $threshold = config('coms.appointment_health.started_late_minutes', 20);
            $started = $done->filter(fn ($a) => $a->actual_start_time && $a->scheduled_start_time);

            $onTime = $started->filter(function ($a) use ($threshold) {
                $scheduled = Carbon::parse($a->scheduled_date->toDateString().' '.$a->scheduled_start_time);
                $actual = Carbon::parse($a->actual_start_time);

                return $actual->lte($scheduled->copy()->addMinutes($threshold));
            })->count();

            $analytics['on_time_rate'] = $started->isNotEmpty()
                ? round($onTime / $started->count() * 100, 1)
                : null;
        }

        if (in_array($role, ['sale', 'admin'])) {
            $analytics['by_type'] = [
                'training' => $collection->where('appointment_type', 'training')->count(),
                'demo' => $collection->where('appointment_type', 'demo')->count(),
            ];
            $analytics['unique_clients'] = $collection->pluck('client_id')->filter()->unique()->count();
            $analytics['unique_trainers'] = $collection->pluck('trainer_id')->filter()->unique()->count();
        }

        return $analytics;
    }

    public function showAnalytics(Appointment $appt): array
    {
        $scheduledStart = Carbon::parse($appt->scheduled_date->toDateString().' '.$appt->scheduled_start_time);
        $scheduledEnd = Carbon::parse($appt->scheduled_date->toDateString().' '.$appt->scheduled_end_time);

        $actualDuration = null;
        $startVariance = null;
        $startedOnTime = null;

        if ($appt->actual_start_time) {
            $actualStart = Carbon::parse($appt->actual_start_time);
            $varianceMins = (int) $scheduledStart->diffInMinutes($actualStart, false);
            $startVariance = $varianceMins;
            $startedOnTime = $varianceMins <= config('coms.appointment_health.started_late_minutes', 20);
        }

        if ($appt->actual_start_time && $appt->actual_end_time) {
            $actualDuration = (int) Carbon::parse($appt->actual_start_time)
                ->diffInMinutes(Carbon::parse($appt->actual_end_time));
        }

        return [
            'scheduled_duration_minutes' => (int) $scheduledStart->diffInMinutes($scheduledEnd),
            'actual_duration_minutes' => $actualDuration,
            'started_on_time' => $startedOnTime,
            'start_variance_minutes' => $startVariance,
            'health' => $this->healthFlag($appt),
        ];
    }

    public function healthFlag(Appointment $appt): array
    {
        $now = Carbon::now();
        $status = $appt->status;
        $cfg = config('coms.appointment_health');

        $dateStart = $appt->scheduled_date->copy()->startOfDay();
        $today = $now->copy()->startOfDay();
        $scheduledStart = Carbon::parse($appt->scheduled_date->toDateString().' '.$appt->scheduled_start_time);
        $scheduledEnd = Carbon::parse($appt->scheduled_date->toDateString().' '.$appt->scheduled_end_time);

        if ($status === 'pending') {
            if ($dateStart->lt($today)) {
                $days = (int) $dateStart->diffInDays($today);

                return ['flag' => 'overdue', 'severity' => 'critical',
                    'detail' => "Scheduled {$days} day(s) ago, still pending"];
            }

            if ($dateStart->eq($today) && $now->gt($scheduledStart->copy()->addMinutes($cfg['starting_late_minutes']))) {
                $mins = (int) $scheduledStart->diffInMinutes($now);

                return ['flag' => 'starting_late', 'severity' => 'warning',
                    'detail' => "{$mins} minute(s) past scheduled start"];
            }

            if (
                $appt->created_at &&
                Carbon::parse($appt->created_at)->diffInDays($now) >= $cfg['pending_too_long_days'] &&
                $dateStart->gt($today)
            ) {
                $days = (int) Carbon::parse($appt->created_at)->diffInDays($now);

                return ['flag' => 'pending_too_long', 'severity' => 'warning',
                    'detail' => "Pending for {$days} day(s)"];
            }

            return ['flag' => 'upcoming', 'severity' => 'info', 'detail' => null];
        }

        if ($status === 'leave_office') {
            if ($now->gt($scheduledStart->copy()->addMinutes($cfg['late_to_client_minutes']))) {
                $mins = (int) $scheduledStart->diffInMinutes($now);

                return ['flag' => 'late_to_client', 'severity' => 'warning',
                    'detail' => "Trainer is {$mins} minute(s) late to arrive"];
            }

            return ['flag' => 'en_route', 'severity' => 'info', 'detail' => null];
        }

        if ($status === 'in_progress') {
            if ($now->gt($scheduledEnd->copy()->addMinutes($cfg['overtime_threshold_minutes']))) {
                $mins = (int) $scheduledEnd->diffInMinutes($now);

                return ['flag' => 'running_overtime', 'severity' => 'warning',
                    'detail' => "{$mins} minute(s) over scheduled end time"];
            }

            if ($appt->actual_start_time) {
                $late = (int) $scheduledStart->diffInMinutes(Carbon::parse($appt->actual_start_time), false);
                if ($late > $cfg['started_late_minutes']) {
                    return ['flag' => 'started_late', 'severity' => 'info',
                        'detail' => "Started {$late} minute(s) late"];
                }
            }

            return ['flag' => 'in_session', 'severity' => 'info', 'detail' => null];
        }

        if ($status === 'done') {
            if ($appt->actual_end_time) {
                $variance = (int) $scheduledEnd->diffInMinutes(Carbon::parse($appt->actual_end_time), false);
                if ($variance > 0) {
                    return ['flag' => 'completed_late', 'severity' => 'info',
                        'detail' => "Ended {$variance} minute(s) after scheduled"];
                }
                $early = abs($variance);

                return ['flag' => 'completed_on_time', 'severity' => 'info',
                    'detail' => $early > 0 ? "Ended {$early} minute(s) early" : null];
            }

            return ['flag' => 'completed_on_time', 'severity' => 'info', 'detail' => null];
        }

        return ['flag' => $status, 'severity' => 'info', 'detail' => null];
    }
}
