<?php

namespace App\Mail;

use App\Models\OnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingFeedbackMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly OnboardingRequest $onboarding,
        public readonly string $rawToken,
        public readonly string $clientEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Rate Your Onboarding Experience — {$this->onboarding->request_code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.onboarding-feedback',
            with: [
                'onboarding' => $this->onboarding,
                'rawToken' => $this->rawToken,
                'feedbackUrl' => url("/api/v1/feedback/$this->rawToken"),
                'expiryDays' => config('coms.feedback_token_ttl_days', 7),
                'companyName' => $this->onboarding->client?->company_name ?? 'Valued Client',
            ],
        );
    }
}
