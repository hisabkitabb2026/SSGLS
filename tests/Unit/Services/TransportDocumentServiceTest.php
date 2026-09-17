<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Services\Document\TransportDocumentService;
use Tests\TestCase;

class TransportDocumentServiceTest extends TestCase
{
    private TransportDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransportDocumentService::class);
    }

    public function test_detects_transport_template(): void
    {
        $lrReceipt = new Invoice;
        $lrReceipt->template_name = 'lr_receipt';
        $this->assertTrue($this->service->isTransportTemplate($lrReceipt));

        $lorryReceipt = new Invoice;
        $lorryReceipt->template_name = 'lorry_receipt';
        $this->assertTrue($this->service->isTransportTemplate($lorryReceipt));

        $officeInvoice = new Invoice;
        $officeInvoice->template_name = 'office_invoice';
        $this->assertTrue($this->service->isTransportTemplate($officeInvoice));

        $standardInvoice = new Invoice;
        $standardInvoice->template_name = 'invoice1';
        $this->assertFalse($this->service->isTransportTemplate($standardInvoice));
    }

    public function test_add_document_prefixes_method_no_longer_exists(): void
    {
        // The addDocumentPrefixes() method was removed because it was broken
        // (looked for wrong field names) and redundant — prefixes are handled
        // by the per-template number format settings configured in
        // Settings → Customization (e.g. lr_receipt_number_format).
        $this->assertFalse(
            method_exists($this->service, 'addDocumentPrefixes'),
            'addDocumentPrefixes() should have been removed from TransportDocumentService.'
        );
    }
}
