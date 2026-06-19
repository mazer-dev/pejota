<?php

namespace App\Services\Invoicing;

class DraftInvoiceItem
{
    /**
     * @param  array<int, int>  $sessionIds
     */
    public function __construct(
        public string $name,
        public float $quantity,
        public float $price,
        public float $total,
        public array $sessionIds,
        public bool $uniformRate,
    ) {}
}
