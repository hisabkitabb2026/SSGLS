<?php

namespace App\Domains\Transport\Mail;

use App\Domains\Transport\Models\LorryReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LorryReceiptNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LorryReceipt $receipt) {}

    public function envelope()
    {
        return [
            'subject' => "Lorry Receipt #{$this->receipt->id} Created",
        ];
    }

    public function content()
    {
        return view('emails.lorry_receipt_created', [
            'receipt' => $this->receipt,
        ]);
    }

    public function attachments()
    {
        return [];
    }
}
