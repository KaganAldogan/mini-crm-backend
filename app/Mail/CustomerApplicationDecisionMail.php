<?php

namespace App\Mail;

use App\Models\CustomerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerApplicationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CustomerApplication $application
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->application->status === 'approved'
            ? 'NeuEmlakCRM — Başvurunuz onaylandı'
            : 'NeuEmlakCRM — Başvurunuz hakkında';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.customer-application-decision',
            with: [
                'application' => $this->application,
                'approved' => $this->application->status === 'approved',
            ],
        );
    }
}
