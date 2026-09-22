<?php

namespace App\Mail;

use App\Models\ReservationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReservationRequest $reservation) {}

    public function envelope(): Envelope
    {
        $tour = $this->reservation->tour?->title ?? 'Genel talep';

        return new Envelope(
            subject: 'Yeni rezervasyon talebi: '.$this->reservation->name.' · '.$tour.' ('.$this->reservation->people_count.' kişi)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-request',
            with: [
                'reservation' => $this->reservation,
                'adminUrl' => url('/admin/rezervasyon-talepleri/'.$this->reservation->getKey()),
            ],
        );
    }
}
