<?php

namespace App\Http\Requests;

/**
 * Send estimate request.
 *
 * Extends BaseSendRequest to consolidate email validation logic
 * across all send-document endpoints.
 */
class SendEstimatesRequest extends BaseSendRequest {}
