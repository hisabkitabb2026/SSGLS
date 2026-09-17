<?php

namespace App\Traits;

use App\Models\Address;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\FileDisk;
use App\Models\Setting;
use App\Services\FontService;
use App\Support\Pdf\PdfHtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

trait GeneratesPdfTrait
{
    /**
     * The document-type prefix used for CompanySetting keys.
     *
     * Subclasses should override this to match their document type
     * (e.g. 'invoice', 'estimate', 'payment').  It is used to build
     * setting keys like '{prefix}_company_address_format'.
     *
     * @var string
     */
    protected function documentTypePrefix(): string
    {
        // Default to 'invoice' — override in models that need a different prefix.
        return 'invoice';
    }

    /**
     * Get the company address for the document, formatted per company settings.
     *
     * Shared by Invoice, Estimate, and Payment — each model just needs a
     * different CompanySetting key prefix.
     */
    public function getCompanyAddress(): string|false
    {
        if ($this->company && (! $this->company->address()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting(
            $this->documentTypePrefix().'_company_address_format',
            $this->company_id
        );

        return $this->getFormattedString($format);
    }

    /**
     * Get the customer shipping address for the document.
     */
    public function getCustomerShippingAddress(): string|false
    {
        if ($this->customer && (! $this->customer->shippingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting(
            $this->documentTypePrefix().'_shipping_address_format',
            $this->company_id
        );

        return $this->getFormattedString($format);
    }

    /**
     * Get the customer billing address for the document.
     */
    public function getCustomerBillingAddress(): string|false
    {
        if ($this->customer && (! $this->customer->billingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting(
            $this->documentTypePrefix().'_billing_address_format',
            $this->company_id
        );

        return $this->getFormattedString($format);
    }

    /**
     * Get the sanitized notes for the document.
     */
    public function getNotes(): string
    {
        return PdfHtmlSanitizer::sanitize($this->getFormattedString($this->notes));
    }

    /**
     * Get the email attachment setting for the document.
     */
    public function getEmailAttachmentSetting(): bool
    {
        $asAttachment = CompanySetting::getSetting(
            $this->documentTypePrefix().'_email_attachment',
            $this->company_id
        );

        if ($asAttachment == 'NO') {
            return false;
        }

        return true;
    }

    /**
     * Replace template placeholders in an email body string.
     *
     * Works for both Invoice (getEmailString) and Estimate/Payment (getEmailBody).
     */
    public function getEmailBody(string $body): string
    {
        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $body = strtr($body, $values);

        return preg_replace('/{(.*?)}/', '', $body);
    }

    public function getGeneratedPDFOrStream($collection_name)
    {
        $pdf = $this->getGeneratedPDF($collection_name);
        if ($pdf && file_exists($pdf['path'])) {
            return response()->make(file_get_contents($pdf['path']), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$pdf['file_name'].'"',
            ]);
        }

        $locale = CompanySetting::getSetting('language', $this->company_id);

        App::setLocale($locale);
        app(FontService::class)->ensureFontsForLocale($locale);

        $pdf = $this->getPDFData();

        return response()->make($pdf->stream(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this[$collection_name.'_number'].'.pdf"',
        ]);
    }

    public function getGeneratedPDF($collection_name)
    {
        try {
            $media = $this->getMedia($collection_name)->first();

            if ($media) {
                $file_disk = FileDisk::find($media->custom_properties['file_disk_id']);

                if (! $file_disk) {
                    return false;
                }

                $file_disk->setConfig();

                $path = null;

                if ($file_disk->driver == 'local') {
                    $path = $media->getPath();
                } else {
                    $path = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
                }

                return collect([
                    'path' => $path,
                    'file_name' => $media->file_name,
                ]);
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    public function generatePDF($collection_name, $file_name, $deleteExistingFile = false)
    {
        $save_pdf_to_disk = Setting::getSetting('save_pdf_to_disk') ?? 'NO';

        if ($save_pdf_to_disk == 'NO') {
            return 0;
        }

        $locale = CompanySetting::getSetting('language', $this->company_id);

        App::setLocale($locale);
        app(FontService::class)->ensureFontsForLocale($locale);

        $pdf = $this->getPDFData();

        \Storage::disk('local')->put('temp/'.$collection_name.'/'.$this->id.'/temp.pdf', $pdf->output());

        if ($deleteExistingFile) {
            $this->clearMediaCollection($this->id);
        }

        $file_disk = FileDisk::whereSetAsDefault(true)->first();

        if ($file_disk) {
            $file_disk->setConfig();
        }

        $media = \Storage::disk('local')->path('temp/'.$collection_name.'/'.$this->id.'/temp.pdf');

        try {
            $this->addMedia($media)
                ->withCustomProperties(['file_disk_id' => $file_disk->id])
                ->usingFileName($file_name.'.pdf')
                ->toMediaCollection($collection_name, config('filesystems.default'));

            \Storage::disk('local')->deleteDirectory('temp/'.$collection_name.'/'.$this->id);

            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getFieldsArray()
    {
        $customer = $this->customer;

        // Transport receipt templates (lr_receipt, lorry_receipt, office_invoice)
        // may not have a customer attached — the Party is a LorryPartyProfile
        // instead of a Customer.  Use a blank Customer instance to avoid
        // "Attempt to read property on null" errors during PDF generation.
        if (! $customer) {
            $customer = new Customer;
        }

        $shippingAddress = $customer->shippingAddress ?? new Address;
        $billingAddress = $customer->billingAddress ?? new Address;
        $companyAddress = $this->company->address ?? new Address;

        $fields = [
            '{SHIPPING_ADDRESS_NAME}' => $shippingAddress->name,
            '{SHIPPING_COUNTRY}' => $shippingAddress->country_name,
            '{SHIPPING_STATE}' => $shippingAddress->state,
            '{SHIPPING_CITY}' => $shippingAddress->city,
            '{SHIPPING_ADDRESS_STREET_1}' => $shippingAddress->address_street_1,
            '{SHIPPING_ADDRESS_STREET_2}' => $shippingAddress->address_street_2,
            '{SHIPPING_PHONE}' => $shippingAddress->phone,
            '{SHIPPING_ZIP_CODE}' => $shippingAddress->zip,
            '{BILLING_ADDRESS_NAME}' => $billingAddress->name,
            '{BILLING_COUNTRY}' => $billingAddress->country_name,
            '{BILLING_STATE}' => $billingAddress->state,
            '{BILLING_CITY}' => $billingAddress->city,
            '{BILLING_ADDRESS_STREET_1}' => $billingAddress->address_street_1,
            '{BILLING_ADDRESS_STREET_2}' => $billingAddress->address_street_2,
            '{BILLING_PHONE}' => $billingAddress->phone,
            '{BILLING_ZIP_CODE}' => $billingAddress->zip,
            '{COMPANY_NAME}' => $this->company->name,
            '{COMPANY_COUNTRY}' => $companyAddress->country_name,
            '{COMPANY_STATE}' => $companyAddress->state,
            '{COMPANY_CITY}' => $companyAddress->city,
            '{COMPANY_ADDRESS_STREET_1}' => $companyAddress->address_street_1,
            '{COMPANY_ADDRESS_STREET_2}' => $companyAddress->address_street_2,
            '{COMPANY_PHONE}' => $companyAddress->phone,
            '{COMPANY_ZIP_CODE}' => $companyAddress->zip,
            '{COMPANY_VAT}' => $this->company->vat_id,
            '{COMPANY_TAX}' => $this->company->tax_id,
            '{CONTACT_DISPLAY_NAME}' => $customer->name,
            '{PRIMARY_CONTACT_NAME}' => $customer->contact_name,
            '{CONTACT_EMAIL}' => $customer->email,
            '{CONTACT_PHONE}' => $customer->phone,
            '{CONTACT_WEBSITE}' => $customer->website,
            '{CONTACT_TAX_ID}' => __('pdf_tax_id').': '.$customer->tax_id,
        ];

        $customFields = $this->fields;
        $customerCustomFields = $customer->fields ?? collect();

        foreach ($customFields as $customField) {
            $fields['{'.$customField->customField->slug.'}'] = $customField->defaultAnswer;
        }

        foreach ($customerCustomFields as $customField) {
            $fields['{'.$customField->customField->slug.'}'] = $customField->defaultAnswer;
        }

        foreach ($fields as $key => $field) {
            $fields[$key] = htmlspecialchars($field, ENT_QUOTES, 'UTF-8');
        }

        return $fields;
    }

    public function getFormattedString($format)
    {
        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $str = nl2br(strtr($format, $values));

        $str = preg_replace('/{(.*?)}/', '', $str);

        $str = preg_replace("/<[^\/>]*>([\s]?)*<\/[^>]*>/", '', $str);

        $str = str_replace('<p>', '', $str);

        $str = str_replace('</p>', '<br />', $str);

        // Sanitize the assembled HTML to strip any SSRF vectors that may have
        // entered through user-supplied address fields, customer names, or
        // custom field values. Notes also pass through this method, so they
        // get the same treatment without needing a separate wrapper.
        return PdfHtmlSanitizer::sanitize($str);
    }
}
