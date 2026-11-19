<?php

namespace App\Mail;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public ?string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your slot has been cancelled');
    }

    public function content(): Content
    {
        $slot = CarbonImmutable::parse($this->booking->starts_at)
            ->setTimezone((string) config('booking.timezone'))
            ->format('d.m.Y H:i');

        $order = e($this->booking->order_number);
        $bookingUrl = url('/book');

        $becauseOf = $this->reason === null
            ? ''
            : '<p>Reason: '.e($this->reason).'</p>';

        return new Content(htmlString: <<<HTML
            <p>Hello,</p>
            <p>the warehouse has cancelled your slot on {$slot} for order {$order}.</p>
            {$becauseOf}
            <p>You can book another slot here:</p>
            <p><a href="{$bookingUrl}">{$bookingUrl}</a></p>
            HTML);
    }
}
