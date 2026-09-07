<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an attempt is made to modify an invoice that is no longer in the
 * editable DRAFT state (422, error code INVOICE_NOT_EDITABLE).
 */
final class InvoiceNotEditableException extends HttpException
{
    public function __construct(string $message = 'Only draft invoices can be edited.')
    {
        parent::__construct(422, $message);
    }
}
