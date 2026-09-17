<?php

namespace App\Services\Document;

use App;
use App\Facades\Pdf;
use App\Mail\SendDocumentMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CustomField;
use App\Models\ExchangeRateLog;
use App\Services\Mail\CompanyMailConfigService;
use App\Support\Pdf\PdfTemplateUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Abstract base class for document services (Invoice, Estimate, Payment).
 *
 * Provides the shared `send()` email-sending pattern and `delete()` collection-deletion
 * pattern that are identical across all three document services.  Subclasses
 * implement the abstract template methods for document-specific logic.
 *
 * Future document types (credit notes, lorry receipts, etc.) can extend this
 * class to inherit the common email/PDF/delete behaviour without duplicating it.
 */
abstract class BaseDocumentService
{
    /**
     * Document-type configuration for SendDocumentMail.
     *
     * Expected keys:
     *  - model_class   : Fully-qualified model class
     *  - data_key      : Key inside $data that holds the model array
     *  - route_name    : Route name for the public viewer link
     *  - template       : Markdown template path
     *  - number_field  : Field name for the PDF filename
     */
    protected array $mailType;

    /**
     * Send a document email with PDF attachment.
     *
     * This is the shared send() pattern used by InvoiceService, EstimateService,
     * and PaymentService.  Subclasses provide the document-specific data via
     * prepareSendData() and set $mailType.
     *
     * @param  Model  $document  The document model (Invoice, Estimate, Payment).
     * @param  array  $data  Email payload (to, cc, bcc, subject, body).
     * @return array ['success' => true, 'type' => 'send']
     *
     * @throws ValidationException When the mail fails to send.
     */
    public function sendDocument(Model $document, array $data): array
    {
        $data = $this->prepareSendData($document, $data);

        CompanyMailConfigService::apply($document->company_id);

        $mail = \Mail::to($data['to']);
        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }
        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }

        try {
            $mailableClass = $this->mailType['mailable_class'] ?? SendDocumentMail::class;
            $mail->send(new $mailableClass($data, $this->mailType));
        } catch (\Throwable $e) {
            \Log::error('Failed to send document email: '.$e->getMessage());
            throw ValidationException::withMessages([
                'mail' => ['Failed to send email. Please check your mail SMTP configuration under Settings. Details: '.$e->getMessage()],
            ]);
        }

        return [
            'success' => true,
            'type' => 'send',
        ];
    }

    /**
     * Delete a collection of documents by ID.
     *
     * Shared deletion pattern — iterates IDs, finds each model, and deletes it.
     * Subclasses can override to add pre-deletion cleanup (e.g. InvoiceService
     * deletes transactions before the invoice).
     *
     * @param  Collection  $ids  Collection of model IDs to delete.
     */
    public function delete(Collection $ids): bool
    {
        foreach ($ids as $id) {
            $document = $this->getModelClass()::find($id);
            if ($document) {
                $this->beforeDelete($document);
                $document->delete();
            }
        }

        return true;
    }

    /**
     * Hook for pre-deletion cleanup. Override in subclasses if needed.
     */
    protected function beforeDelete(Model $document): void
    {
        // Default: no-op. Subclasses can override.
    }

    /**
     * Public wrapper for prepareSendData(), used by controller sendPreview endpoints.
     *
     * This avoids each subclass needing a type-specific public method
     * (e.g. sendInvoiceData, sendEstimateData, sendPaymentData).
     */
    public function getSendData(Model $document, array $data): array
    {
        return $this->prepareSendData($document, $data);
    }

    /**
     * Aggregate item-level taxes into a single collection.
     *
     * When `tax_per_item` is enabled on the document, each line item carries its
     * own tax rows.  For PDF display we need to collapse those into one entry per
     * tax_type_id, summing the amounts.  This logic was previously duplicated
     * verbatim in InvoiceService::getPdfData() and EstimateService::getPdfData().
     *
     * @param  Model  $document  Document with ->items and ->items[].taxes loaded.
     * @return Collection Aggregated tax objects keyed by tax_type_id.
     */
    protected function aggregateItemTaxes(Model $document): Collection
    {
        $taxes = collect();

        if ($document->tax_per_item === 'YES') {
            foreach ($document->items as $item) {
                foreach ($item->taxes as $tax) {
                    $found = $taxes->firstWhere('tax_type_id', $tax->tax_type_id);

                    if ($found) {
                        $found->amount += $tax->amount;
                    } else {
                        $taxes->push($tax);
                    }
                }
            }
        }

        return $taxes;
    }

    /**
     * Copy custom fields from one document to another.
     *
     * Used by clone(), convertToInvoice(), convertToEstimate(), and
     * createInvoiceFromRecurring() — previously duplicated 5 times.
     *
     * @param  Model  $source  Document whose custom fields to read.
     * @param  Model  $target  Document to receive the copied fields.
     */
    protected function copyCustomFields(Model $source, Model $target): void
    {
        if ($source->fields()->exists()) {
            $customFields = [];

            foreach ($source->fields as $data) {
                $customFields[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }

            $target->addCustomFields($customFields);
        }
    }

    /**
     * Log an exchange rate entry if the document's currency differs from
     * the company's base currency.
     *
     * Previously duplicated 10+ times across create()/update() in all
     * document services.
     *
     * @param  Model  $document  Document with ->currency_id.
     * @param  string|null  $companyId  Company ID (from request header or model).
     */
    protected function logExchangeRateIfNeeded(Model $document, ?string $companyId): void
    {
        $companyCurrency = CompanySetting::getSetting('currency', $companyId);

        if ((string) $document->currency_id !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($document);
        }
    }

    /**
     * Prepare shared PDF view scaffolding (company, locale, logo, view shares,
     * template resolution) and return either a rendered View or a Pdf instance.
     *
     * Previously duplicated across InvoiceService, EstimateService, and
     * PaymentService getPdfData() methods.  Each subclass now calls this and
     * passes only the document-specific extra view data.
     *
     * @param  Model  $document  The document model.
     * @param  array  $extraViewData  Document-specific data to share with the view.
     * @param  string  $templateType  Template type ('invoice', 'estimate', 'payment').
     * @param  string  $templateName  Specific template name within the type.
     * @return mixed \Illuminate\View\View (preview) or \Barryvdh\DomPDF\PDF (download).
     */
    protected function preparePdfView(Model $document, array $extraViewData, string $templateType, string $templateName): mixed
    {
        $company = Company::find($document->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);

        App::setLocale($locale);

        $customFields = CustomField::where('model_type', 'Item')->get();

        view()->share(array_merge([
            'customFields' => $customFields,
            'logo' => $company->logo_path ?? null,
            'company_address' => $document->getCompanyAddress(),
            'shipping_address' => $document->getCustomerShippingAddress(),
            'billing_address' => $document->getCustomerBillingAddress(),
            'notes' => $document->getNotes(),
        ], $extraViewData));

        $template = PdfTemplateUtils::findFormattedTemplate($templateType, $templateName, '');

        // Fallback: if the stored template_name doesn't match any available
        // template file (e.g. custom templates were removed or the name was
        // set to a non-existent value), fall back to the first available
        // template so PDF generation doesn't crash with a 500 error.
        if ($template === null) {
            $allTemplates = PdfTemplateUtils::getFormattedTemplates($templateType, '');
            $template = $allTemplates[0] ?? null;
            $templateName = $template['name'] ?? $templateName;
        }

        $templatePath = $template['custom']
            ? sprintf('pdf_templates::%s.%s', $templateType, $templateName)
            : sprintf('app.pdf.%s.%s', $templateType, $templateName);

        if (request()->has('preview')) {
            return view($templatePath);
        }

        $pdf = Pdf::loadView($templatePath);

        // Transport receipt templates (lr_receipt, lorry_receipt, office_invoice)
        // use landscape A4 layout. dompdf ignores the @page CSS size rule, so
        // we must call setPaper() programmatically. Gotenberg uses its own
        // paper size config, so method_exists guards against calling
        // setPaper() on a GotenbergPdfResponse.
        if (
            $templateType === 'invoice'
            && in_array($templateName, ['lr_receipt', 'lorry_receipt', 'lorry_receipt_landscape', 'office_invoice'])
            && method_exists($pdf, 'setPaper')
        ) {
            $pdf->setPaper('a4', 'landscape');
        }

        // Estimate templates use landscape A4 for the Rate Card Matrix layout.
        if (
            $templateType === 'estimate'
            && method_exists($pdf, 'setPaper')
        ) {
            $pdf->setPaper('a4', 'landscape');
        }

        return $pdf;
    }

    /**
     * Get the fully-qualified model class name for this service.
     *
     * @return class-string<Model>
     */
    abstract protected function getModelClass(): string;

    /**
     * Prepare the email send data for a document.
     *
     * Subclasses must implement this to populate the $data array with
     * the document array, customer, company, subject, body, and PDF attachment.
     */
    abstract protected function prepareSendData(Model $document, array $data): array;
}
