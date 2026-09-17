<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Unified queued job for generating PDFs for any document model that uses
 * the GeneratesPdfTrait (Invoice, Estimate, Payment, and future document types).
 *
 * Replaces the three identical 42-line jobs:
 * - GenerateInvoicePdfJob
 * - GenerateEstimatePdfJob
 * - GeneratePaymentPdfJob
 *
 * The job accepts the model instance, a document-type string (passed to
 * generatePDF as the first argument), and the number field name used to
 * generate the PDF filename.
 */
class GenerateDocumentPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The document model instance (Invoice, Estimate, Payment, etc.).
     *
     * @var mixed
     */
    public $document;

    /**
     * Whether to delete the existing PDF file before regenerating.
     *
     * @var bool
     */
    public $deleteExistingFile;

    /**
     * Document type identifier passed to GeneratesPdfTrait::generatePDF().
     * e.g. 'invoice', 'estimate', 'payment'.
     */
    protected string $type;

    /**
     * Field name on the model that holds the document number (used for the PDF filename).
     * e.g. 'invoice_number', 'estimate_number', 'payment_number'.
     */
    protected string $numberField;

    /**
     * Create a new job instance.
     *
     * @param  mixed  $document  The document model instance.
     * @param  string  $type  Document type ('invoice', 'estimate', 'payment').
     * @param  string  $numberField  Number field name on the model.
     * @param  bool  $deleteExistingFile  Whether to delete the existing PDF first.
     */
    public function __construct($document, string $type, string $numberField, bool $deleteExistingFile = false)
    {
        $this->document = $document;
        $this->type = $type;
        $this->numberField = $numberField;
        $this->deleteExistingFile = $deleteExistingFile;
    }

    /**
     * Execute the job.
     */
    public function handle(): int
    {
        $this->document->generatePDF(
            $this->type,
            $this->document->{$this->numberField},
            $this->deleteExistingFile
        );

        return 0;
    }
}
