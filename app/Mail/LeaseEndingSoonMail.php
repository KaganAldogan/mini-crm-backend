<?php

namespace App\Mail;

use App\Models\Lease;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaseEndingSoonMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lease $lease,
        public User $user,
        public int $daysLeft
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NeuEmlakCRM — Sözleşme bitiş hatırlatması'
        );
    }

    public function content(): Content
    {
        $portalPath = '/dashboard';

        return new Content(
            markdown: 'mail.lease-ending-soon',
            with: [
                'user' => $this->user,
                'lease' => $this->lease,
                'daysLeft' => $this->daysLeft,
                'endDate' => $this->lease->end_date?->format('d.m.Y'),
                'propertyTitle' => $this->lease->property?->title ?? 'Sözleşme',
                'portalUrl' => rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/').$portalPath,
            ],
        );
    }
}
