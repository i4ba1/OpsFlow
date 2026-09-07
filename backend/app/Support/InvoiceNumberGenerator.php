<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe per-organization/per-year invoice numbering (PRD §17):
 * INV-{year}-{6-digit}, e.g. INV-2026-000001.
 *
 * Never derived from count(*)+1 — the next number is allocated by locking the
 * invoice_sequences row for the organization and year, incrementing it, and
 * only creating the row when it does not exist yet. Two parallel issuers
 * serialize on the row lock, so no number can be handed out twice.
 */
final class InvoiceNumberGenerator
{
    public function __construct(
        private readonly \DateTimeInterface $clock = new \DateTimeImmutable,
    ) {}

    public function next(string $organizationId): string
    {
        $year = (int) $this->clock->format('Y');

        return DB::transaction(function () use ($organizationId, $year): string {
            $sequence = DB::table('invoice_sequences')
                ->where('organization_id', $organizationId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                DB::table('invoice_sequences')->insert([
                    'organization_id' => $organizationId,
                    'year' => $year,
                    'last_value' => 1,
                ]);

                return $this->format($year, 1);
            }

            $lastValue = (int) $sequence->last_value + 1;

            DB::table('invoice_sequences')
                ->where('organization_id', $organizationId)
                ->where('year', $year)
                ->update(['last_value' => $lastValue]);

            return $this->format($year, $lastValue);
        });
    }

    private function format(int $year, int $sequence): string
    {
        return sprintf('INV-%d-%06d', $year, $sequence);
    }
}
