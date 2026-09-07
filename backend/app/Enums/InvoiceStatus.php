<?php

namespace App\Enums;

/**
 * Invoice lifecycle (PRD §17). Draft invoices are editable and become ISSUED
 * through the issue action (which allocates the INV number); PAID invoices are
 * terminal; VOID invoices are terminal. The MarkOverdueInvoices job flips
 * ISSUED invoices whose due date has passed to OVERDUE, and OVERDUE invoices
 * can still be marked paid.
 */
enum InvoiceStatus: string
{
    case Draft = 'DRAFT';
    case Issued = 'ISSUED';
    case Paid = 'PAID';
    case Overdue = 'OVERDUE';
    case Void = 'VOID';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Void => 'Void',
        };
    }
}
