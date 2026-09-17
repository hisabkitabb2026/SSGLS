<?php

namespace App\Domains\Transport\Mail;

use App\Domains\Transport\Models\ConsolidationGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsolidationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ConsolidationGroup $consolidation) {}

    public function envelope()
    {
        return [
            'subject' => "Consolidation #{$this->consolidation->id} - {$this->consolidation->name}",
        ];
    }

    public function content()
    {
        return view('emails.consolidation_created', [
            'consolidation' => $this->consolidation,
        ]);
    }

    public function attachments()
    {
        return [];
    }
}
