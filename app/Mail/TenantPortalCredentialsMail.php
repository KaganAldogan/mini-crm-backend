<?php

namespace App\Mail;

use App\Models\CustomerApplication;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantPortalCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CustomerApplication $application,
        public User $user,
        public ?string $plainPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NeuEmlakCRM — Kiracı portalı hesabınız hazır'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tenant-portal-credentials',
            with: [
                'application' => $this->application,
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'loginUrl' => rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/').'/login',
            ],
        );
    }
}
