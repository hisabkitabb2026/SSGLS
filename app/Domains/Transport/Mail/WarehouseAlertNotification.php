<?php

namespace App\Domains\Transport\Mail;

use App\Domains\Transport\Models\WarehouseItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WarehouseAlertNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WarehouseItem $item, public string $reason = 'status_change') {}

    public function envelope()
    {
        return [
            'subject' => "Warehouse Item #{$this->item->id} Update: {$this->reason}",
        ];
    }

    public function content()
    {
        return view('emails.warehouse_alert', [
            'item' => $this->item,
            'reason' => $this->reason,
        ]);
    }

    public function attachments()
    {
        return [];
    }
}
