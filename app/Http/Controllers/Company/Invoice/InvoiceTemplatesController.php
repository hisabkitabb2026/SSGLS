<?php

namespace App\Http\Controllers\Company\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Pdf\PdfTemplateUtils;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceTemplatesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $documentType = $request->get('document_type');
        $invoiceTemplates = PdfTemplateUtils::getFormattedTemplates('invoice');

        // When a document_type is specified (e.g. 'lr_receipt', 'lorry_receipt',
        // 'office_invoice'), filter to only show templates matching that type.
        // This allows the template selector to show relevant templates per
        // document type. When no document_type is specified, return all
        // templates (standard invoice behavior).
        if ($documentType) {
            $invoiceTemplates = array_filter($invoiceTemplates, function ($template) use ($documentType) {
                // Match exact type (e.g. 'lr_receipt') or numbered variants
                // (e.g. 'lr_receipt2', 'lr_receipt3') for future extensibility.
                return $template['name'] === $documentType
                    || str_starts_with($template['name'], $documentType);
            });
            $invoiceTemplates = array_values($invoiceTemplates);
        } else {
            // For standard invoices (no document_type), exclude transport
            // receipt templates so only invoice1, invoice2, etc. are shown.
            $transportTypes = ['lr_receipt', 'lorry_receipt', 'office_invoice'];
            $invoiceTemplates = array_filter($invoiceTemplates, function ($template) use ($transportTypes) {
                foreach ($transportTypes as $type) {
                    if ($template['name'] === $type || str_starts_with($template['name'], $type)) {
                        return false;
                    }
                }

                return true;
            });
            $invoiceTemplates = array_values($invoiceTemplates);
        }

        return response()->json([
            'invoiceTemplates' => $invoiceTemplates,
        ]);
    }
}
