<?php

namespace App\Domains\Invoicing\Mail;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceCreatedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope()
    {
        return [
            'subject' => "Invoice #{$this->invoice->invoice_number} Created",
        ];
    }

    public function content()
    {
        return view('emails.invoice_created', [
            'invoice' => $this->invoice,
        ]);
    }

    public function attachments()
    {
        return [];
    }
}
