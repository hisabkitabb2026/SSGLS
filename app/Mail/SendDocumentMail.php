<?php

namespace App\Mail;

use App\Facades\Hashids;
use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Unified mail class for sending documents (invoices, estimates, payments) via email.
 *
 * Replaces the three near-identical classes:
 * - SendInvoiceMail
 * - SendEstimateMail
 * - SendPaymentMail
 *
 * The document type, mailable class, route name, markdown template, and PDF
 * filename field are all driven by the $type configuration array so that a single
 * class handles every document type.  Future document types (e.g. credit notes,
 * lorry receipts) can be added by extending the type map without creating a new
 * Mailable subclass.
 */
class SendDocumentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Email payload + document data.
     *
     * @var array
     */
    public $data = [];

    /**
     * Document-type configuration.
     *
     * Expected keys:
     *  - model_class   : Fully-qualified model class (stored in email_log.mailable_type)
     *  - data_key      : Key inside $data that holds the model array (e.g. 'invoice', 'estimate', 'payment')
     *  - route_name    : Route name for the public viewer link
     *  - template       : Markdown template path (e.g. 'emails.send.invoice')
     *  - number_field  : Field name for the PDF filename (e.g. 'invoice_number')
     */
    protected array $type;

    /**
     * Create a new document email message instance.
     *
     * @param  array  $data  Email payload data.
     * @param  array  $type  Document-type configuration (see $type docblock).
     */
    public function __construct(array $data, array $type)
    {
        $this->data = $data;
        $this->type = $type;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $dataKey = $this->type['data_key'];

        $log = EmailLog::create([
            'from' => $this->data['from'],
            'to' => $this->data['to'],
            'cc' => $this->data['cc'] ?? null,
            'bcc' => $this->data['bcc'] ?? null,
            'subject' => $this->data['subject'],
            'body' => $this->data['body'],
            'mailable_type' => $this->type['model_class'],
            'mailable_id' => $this->data[$dataKey]['id'],
        ]);

        $log->token = Hashids::connection(EmailLog::class)->encode($log->id);
        $log->save();

        $this->data['url'] = route($this->type['route_name'], ['email_log' => $log->token]);

        $mailContent = $this->from($this->data['from'], config('mail.from.name'))
            ->subject($this->data['subject'])
            ->markdown($this->type['template'], ['data', $this->data]);

        if ($this->data['attach']['data']) {
            $mailContent->attachData(
                $this->data['attach']['data']->output(),
                $this->data[$dataKey][$this->type['number_field']].'.pdf'
            );
        }

        return $mailContent;
    }
}
