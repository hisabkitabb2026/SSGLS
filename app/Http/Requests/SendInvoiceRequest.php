<?php

namespace App\Http\Requests;

/**
 * Send invoice request.
 *
 * Extends BaseSendRequest to consolidate email validation logic
 * across all send-document endpoints.
 */
class SendInvoiceRequest extends BaseSendRequest {}
