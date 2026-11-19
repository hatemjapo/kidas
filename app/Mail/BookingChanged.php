<?php

namespace App\Mail;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public CarbonImmutable $movedFrom,
        public ?string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your slot has been changed');
    }

    public function content(): Content
    {
        $before = $this->describe($this->movedFrom);
        $now = $this->describe(CarbonImmutable::parse($this->booking->starts_at));
        $until = $this->describe(CarbonImmutable::parse($this->booking->ends_at), 'H:i');
        $dock = $this->booking->dock->number;
        $pallets = $this->booking->pallets;
        $order = e($this->booking->order_number);
        $bookingsUrl = url('/bookings');

        $becauseOf = $this->reason === null
            ? ''
            : '<p>Reason: '.e($this->reason).'</p>';

        return new Content(htmlString: <<<HTML
            <p>Hello,</p>
            <p>the warehouse has changed your slot for order {$order}.</p>
            <p>Previously: {$before}</p>
            <p>Now: {$now} &ndash; {$until}, dock {$dock}, {$pallets} pallets</p>
            {$becauseOf}
            <p><a href="{$bookingsUrl}">{$bookingsUrl}</a></p>
            HTML);
    }

    private function describe(CarbonImmutable $moment, string $format = 'd.m.Y H:i'): string
    {
        return $moment->setTimezone((string) config('booking.timezone'))->format($format);
    }
}
