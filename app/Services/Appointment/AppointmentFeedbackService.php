<?php

namespace App\Services\Appointment;

use App\Exceptions\Business\FeedbackAlreadySubmittedException;
use App\Models\Appointment;
use App\Models\AppointmentFeedback;
use App\Models\AppointmentFeedbackToken;
use App\Models\FeedbackRespondent;
use App\Services\Telegram\TelegramGroupService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Random\RandomException;
use Throwable;

readonly class AppointmentFeedbackService
{
    public function __construct(
        private TelegramGroupService $telegramGroupService,
    ) {}

    /**
     * @throws RandomException
     */
    public function generateAndNotify(Appointment $appt): AppointmentFeedbackToken
    {
        AppointmentFeedbackToken::where('appointment_id', $appt->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $ttlDays = config('coms.feedback_token_ttl_days', 7);

        $tokenRecord = AppointmentFeedbackToken::create([
            'appointment_id' => $appt->id,
            'token' => $hashedToken,
            'expires_at' => now()->addDays($ttlDays),
            'is_active' => true,
        ]);

        $feedbackUrl = config('app.url').'/api/v1/appointments/feedback/'.$rawToken;

        $appt->loadMissing('client');
        $clientName = $appt->client?->company_name ?? 'Client';

        try {
            $this->telegramGroupService->notifyClient(
                $appt->client_id,
                'appointment_feedback_link',
                [
                    'client_name' => $clientName,
                    'appointment_title' => $appt->title,
                    'feedback_url' => $feedbackUrl,
                ]
            );
        } catch (Throwable $e) {
            // Telegram failure must not block token creation
        }

        return $tokenRecord;
    }

    public function getFormContext(string $rawToken): array
    {
        $hashedToken = hash('sha256', $rawToken);

        $tokenRecord = AppointmentFeedbackToken::where('token', $hashedToken)
            ->with('appointment.client')
            ->first();

        if (! $tokenRecord) {
            return ['state' => 'invalid'];
        }

        if (! $tokenRecord->is_active || $tokenRecord->expires_at->isPast()) {
            return ['state' => 'expired', 'appointment' => $tokenRecord->appointment];
        }

        return [
            'state' => 'form',
            'appointment' => $tokenRecord->appointment,
            'token' => $rawToken,
        ];
    }

    /**
     * @throws ValidationException
     * @throws FeedbackAlreadySubmittedException
     * @throws Throwable
     */
    public function submit(string $rawToken, array $data): AppointmentFeedback
    {
        $hashedToken = hash('sha256', $rawToken);

        $tokenRecord = AppointmentFeedbackToken::where('token', $hashedToken)
            ->with('appointment.client')
            ->first();

        if (! $tokenRecord) {
            throw ValidationException::withMessages([
                'token' => ['Invalid feedback token.'],
            ]);
        }

        if (! $tokenRecord->is_active || $tokenRecord->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This feedback link has expired.'],
            ]);
        }

        $appointment = $tokenRecord->appointment;
        $clientId = $appointment->client_id;

        $respondent = FeedbackRespondent::where('email', $data['email'])
            ->where('client_id', $clientId)
            ->first();

        if (! $respondent) {
            $respondent = FeedbackRespondent::create([
                'client_id' => $clientId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? null,
                'position' => $data['position'] ?? null,
            ]);
        }

        $alreadySubmitted = AppointmentFeedback::where('respondent_id', $respondent->id)
            ->where('appointment_id', $appointment->id)
            ->exists();

        if ($alreadySubmitted) {
            throw new FeedbackAlreadySubmittedException(context: [
                'respondent_id' => $respondent->id,
                'appointment_id' => $appointment->id,
            ]);
        }

        $feedback = DB::transaction(function () use ($tokenRecord, $respondent, $appointment, $data) {
            return AppointmentFeedback::create([
                'appointment_id' => $appointment->id,
                'token_id' => $tokenRecord->id,
                'respondent_id' => $respondent->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'submitted_at' => now(),
            ]);
        });

        Cache::store('redis')->forget("appointment:show:{$appointment->id}");

        return $feedback;
    }

    public function getForAppointment(string $appointmentId): Collection
    {
        return AppointmentFeedback::with('respondent')
            ->where('appointment_id', $appointmentId)
            ->orderByDesc('submitted_at')
            ->get();
    }
}
