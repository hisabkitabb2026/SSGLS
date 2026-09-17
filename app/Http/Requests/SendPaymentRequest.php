<?php

namespace App\Http\Requests;

/**
 * Send payment request.
 *
 * Extends BaseSendRequest to consolidate email validation logic
 * across all send-document endpoints.
 */
class SendPaymentRequest extends BaseSendRequest {}
