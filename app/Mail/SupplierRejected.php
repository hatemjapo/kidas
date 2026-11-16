<?php

namespace App\Mail;

use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Supplier $supplier) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your KiDas registration was not approved');
    }

    public function content(): Content
    {
        $company = e($this->supplier->company_name);

        return new Content(htmlString: <<<HTML
            <p>Hello {$company},</p>
            <p>your registration for the KiDas dock appointment system was not approved, so signing in is not possible.</p>
            <p>If you believe this is a mistake, please get in touch with the warehouse team.</p>
            HTML);
    }
}
