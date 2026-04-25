<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $resetLink,
        public readonly int $expiryMinutes
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Reset Request — CheckinMe',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.forgot_password',
            with: [
                'resetLink'     => $this->resetLink,
                'expiryMinutes' => $this->expiryMinutes,
            ],
        );
    }
}
