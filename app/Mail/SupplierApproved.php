<?php

namespace App\Mail;

use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Supplier $supplier) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your KiDas registration is approved');
    }

    public function content(): Content
    {
        $company = e($this->supplier->company_name);
        $loginUrl = url('/login');

        return new Content(htmlString: <<<HTML
            <p>Hello {$company},</p>
            <p>your company is now approved for the KiDas dock appointment system. You can sign in and book your time slots.</p>
            <p><a href="{$loginUrl}">{$loginUrl}</a></p>
            HTML);
    }
}
