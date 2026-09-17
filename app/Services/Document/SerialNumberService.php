<?php

namespace App\Services\Document;

use App\Models\CompanySetting;
use App\Models\Customer;

class SerialNumberService
{
    public const VALID_PLACEHOLDERS = ['CUSTOMER_SERIES', 'SEQUENCE', 'DATE_FORMAT', 'SERIES', 'RANDOM_SEQUENCE', 'DELIMITER', 'CUSTOMER_SEQUENCE'];

    /**
     * Factory method to quickly build a fully-configured SerialNumberService.
     *
     * Replaces the repeated 4–5 line builder chain that was duplicated 10+ times
     * across all document services.
     *
     * @param  mixed  $model  Model instance or class string.
     * @param  int|string  $companyId  Company ID.
     * @param  int|string  $customerId  Customer ID (nullable).
     * @param  int|null  $modelId  Existing model ID for updates (to preserve sequence).
     * @param  string|null  $templateName  Template name for invoice-type filtering.
     */
    public static function for($model, $companyId, $customerId = null, ?int $modelId = null, ?string $templateName = null): self
    {
        $instance = (new self)
            ->setModel($model)
            ->setCompany($companyId)
            ->setCustomer($customerId);

        if ($templateName) {
            $instance->setTemplateName($templateName);
        }

        if ($modelId) {
            $instance->setModelObject($modelId);
        }

        return $instance->setNextNumbers();
    }

    private $model;

    private $ob;

    private $customer;

    private $company;

    /**
     * The template_name for invoice-type models (e.g. 'lr_receipt', 'office_invoice').
     * When set, the service uses '{template_name}_number_format' as the setting key
     * and filters the sequence counter by template_name.
     *
     * @var string|null
     */
    private $templateName;

    /**
     * @var string
     */
    public $nextSequenceNumber;

    /**
     * @var string
     */
    public $nextCustomerSequenceNumber;

    /**
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    public function setModelObject($id = null)
    {
        $this->ob = $this->model::find($id);

        if ($this->ob && $this->ob->sequence_number) {
            $this->nextSequenceNumber = $this->ob->sequence_number;
        }

        if (isset($this->ob) && isset($this->ob->customer_sequence_number) && isset($this->customer) && $this->ob->customer_id == $this->customer->id) {
            $this->nextCustomerSequenceNumber = $this->ob->customer_sequence_number;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setCompany($company)
    {
        $this->company = $company;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCustomer($customer = null)
    {
        $this->customer = Customer::find($customer);

        return $this;
    }

    /**
     * Set the template_name for invoice-type models so the service can
     * use a per-type number format setting and filter the sequence counter.
     *
     * @return $this
     */
    public function setTemplateName(?string $templateName)
    {
        $this->templateName = $templateName;

        return $this;
    }

    /**
     * @return string
     */
    public function getNextNumber($data = null)
    {
        $modelName = strtolower(class_basename($this->model));
        $settingKey = $modelName.'_number_format';

        // For invoice models with a transport template_name, use the per-type format setting
        // (e.g. 'lr_receipt_number_format') instead of the generic 'invoice_number_format'.
        if ($modelName === 'invoice' && $this->templateName && in_array($this->templateName, ['lr_receipt', 'lorry_receipt', 'office_invoice'])) {
            $settingKey = $this->templateName.'_number_format';
        }

        $companyId = $this->company;

        if (request()->has('format')) {
            $format = request()->get('format');
        } else {
            $format = CompanySetting::getSetting(
                $settingKey,
                $companyId
            );
        }
        $this->setNextNumbers();

        $serialNumber = $this->generateSerialNumber(
            $format
        );

        return $serialNumber;
    }

    public function setNextNumbers()
    {
        $this->nextSequenceNumber ?
            $this->nextSequenceNumber : $this->setNextSequenceNumber();

        $this->nextCustomerSequenceNumber ?
            $this->nextCustomerSequenceNumber : $this->setNextCustomerSequenceNumber();

        return $this;
    }

    /**
     * @return $this
     */
    public function setNextSequenceNumber()
    {
        $companyId = $this->company;

        $query = $this->model::orderBy('sequence_number', 'desc')
            ->where('company_id', $companyId)
            ->where('sequence_number', '<>', null);

        // For invoice models with a template_name, filter the sequence counter
        // by template_name so each invoice type has its own independent counter.
        if ($this->templateName && method_exists($this->model, 'getTable')) {
            $table = (new $this->model)->getTable();
            if (in_array('template_name', \Schema::getColumnListing($table))) {
                $query->where('template_name', $this->templateName);
            }
        }

        $last = $query->take(1)->first();

        $this->nextSequenceNumber = ($last) ? $last->sequence_number + 1 : 1;

        return $this;
    }

    /**
     * @return self
     */
    public function setNextCustomerSequenceNumber()
    {
        $customer_id = ($this->customer) ? $this->customer->id : 1;

        $last = $this->model::orderBy('customer_sequence_number', 'desc')
            ->where('company_id', $this->company)
            ->where('customer_id', $customer_id)
            ->where('customer_sequence_number', '<>', null)
            ->take(1)
            ->first();

        $this->nextCustomerSequenceNumber = ($last) ? $last->customer_sequence_number + 1 : 1;

        return $this;
    }

    public static function getPlaceholders(string $format)
    {
        $regex = '/{{([A-Z_]{1,})(?::)?([a-zA-Z0-9_]{1,6}|.{1})?}}/';

        preg_match_all($regex, $format, $placeholders);
        array_shift($placeholders);
        $validPlaceholders = collect();

        /** @var array */
        $mappedPlaceholders = array_map(
            null,
            current($placeholders),
            end($placeholders)
        );

        foreach ($mappedPlaceholders as $placeholder) {
            $name = current($placeholder);
            $value = end($placeholder);

            if (in_array($name, self::VALID_PLACEHOLDERS)) {
                $validPlaceholders->push([
                    'name' => $name,
                    'value' => $value,
                ]);
            }
        }

        return $validPlaceholders;
    }

    /**
     * @return string
     */
    private function generateSerialNumber(string $format)
    {
        $serialNumber = '';

        $placeholders = self::getPlaceholders($format);

        foreach ($placeholders as $placeholder) {
            $name = $placeholder['name'];
            $value = $placeholder['value'];

            switch ($name) {
                case 'SEQUENCE':
                    $value = $value ? $value : 6;
                    $serialNumber .= str_pad($this->nextSequenceNumber, $value, 0, STR_PAD_LEFT);

                    break;
                case 'DATE_FORMAT':
                    $value = $value ? $value : 'Y';
                    $serialNumber .= date($value);

                    break;
                case 'RANDOM_SEQUENCE':
                    $value = $value ? $value : 6;
                    $serialNumber .= substr(bin2hex(random_bytes($value)), 0, $value);

                    break;
                case 'CUSTOMER_SERIES':
                    if (isset($this->customer)) {
                        $serialNumber .= $this->customer->prefix ?? 'CST';
                    } else {
                        $serialNumber .= 'CST';
                    }

                    break;
                case 'CUSTOMER_SEQUENCE':
                    $serialNumber .= str_pad($this->nextCustomerSequenceNumber, $value, 0, STR_PAD_LEFT);

                    break;
                default:
                    $serialNumber .= $value;
            }
        }

        return $serialNumber;
    }
}
