<?php

namespace App\Domains\Customer\Mail;

use App\Domains\Customer\Models\Address;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AddressAddedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Address $address) {}

    public function envelope()
    {
        return [
            'subject' => "New Address Added for {$this->address->customer->name}",
        ];
    }

    public function content()
    {
        return view('emails.address_added', [
            'address' => $this->address,
        ]);
    }

    public function attachments()
    {
        return [];
    }
}
