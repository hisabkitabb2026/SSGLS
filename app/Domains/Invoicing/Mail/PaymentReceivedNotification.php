<?php

namespace App\Domains\Invoicing\Mail;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope()
    {
        return [
            'subject' => "Payment Received for Invoice #{$this->invoice->invoice_number}",
        ];
    }

    public function content()
    {
        return view('emails.payment_received', [
            'invoice' => $this->invoice,
        ]);
    }

    public function attachments()
    {
        return [];
    }
}
