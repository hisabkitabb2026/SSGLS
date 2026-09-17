<?php

namespace App\Domains\Customer\Mail;

use App\Domains\Customer\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerCreatedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Customer $customer) {}

    public function envelope()
    {
        return [
            'subject' => "Customer {$this->customer->name} Added",
        ];
    }

    public function content()
    {
        return view('emails.customer_created', [
            'customer' => $this->customer,
        ]);
    }

    public function attachments()
    {
        return [];
    }
}
