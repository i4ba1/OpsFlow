<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an attempt is made to mark an invoice paid that is already PAID,
 * or to pay/void an invoice in a terminal state that excludes the transition
 * (422, error code INVOICE_ALREADY_PAID).
 */
final class InvoiceAlreadyPaidException extends HttpException
{
    public function __construct(string $message = 'This invoice has already been paid or voided.')
    {
        parent::__construct(422, $message);
    }
}
