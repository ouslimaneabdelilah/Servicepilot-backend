<?php

namespace App\Domain\Invoices\Actions;

class CalculateInvoiceTotalsAction
{
    /**
     * Compute invoice financial totals with decimal-safe precision.
     */
    public function execute(array $items, float $discount = 0.0, float $taxRate = 0.0): array
    {
        $subtotal = 0.0;
        $itemTotals = [];

        foreach ($items as $index => $item) {
            $qty = (float) ($item['quantity'] ?? 1.0);
            $unitPrice = (float) ($item['unit_price'] ?? 0.0);
            $lineTotal = round($qty * $unitPrice, 2);

            $subtotal += $lineTotal;
            $itemTotals[$index] = $lineTotal;
        }

        $subtotal = round($subtotal, 2);
        $discount = min($subtotal, max(0.0, round($discount, 2)));
        $taxable = max(0.0, $subtotal - $discount);
        $taxAmount = round($taxable * $taxRate, 2);
        $total = round($taxable + $taxAmount, 2);

        return [
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'tax_amount'      => $taxAmount,
            'total'           => $total,
            'item_totals'     => $itemTotals,
        ];
    }
}

