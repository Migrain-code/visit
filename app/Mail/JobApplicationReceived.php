<?php

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public JobApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yeni iş başvurusu: '.$this->application->name.($this->application->position ? ' · '.$this->application->position : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.job-application',
            with: [
                'application' => $this->application,
                'adminUrl' => url('/admin/is-basvurulari'),
            ],
        );
    }
}
