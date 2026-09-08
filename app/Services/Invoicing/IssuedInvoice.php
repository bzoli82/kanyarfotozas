<?php

namespace App\Services\Invoicing;

/**
 * Egy kiállított (vagy sztornó) számla a szolgáltatótól.
 */
class IssuedInvoice
{
    public function __construct(
        public string $externalId,
        public ?string $number,
        public int $grossCents,
        /** A számla PDF nyers tartalma (ha a szolgáltató visszaadja). */
        public ?string $pdf = null,
    ) {}
}
