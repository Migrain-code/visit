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
        return new Envelope(
            subject: 'Yeni iletişim talebi: '.$this->reservation->name.' · '.$this->reservation->tour_label.' ('.$this->reservation->people_count.' kişi)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-request',
            with: [
                'reservation' => $this->reservation,
                'adminUrl' => url('/admin/iletisim-talepleri/'.$this->reservation->getKey()),
            ],
        );
    }
}
