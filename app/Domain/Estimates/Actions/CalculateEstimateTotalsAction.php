<?php

namespace App\Domain\Estimates\Actions;

class CalculateEstimateTotalsAction
{
    /**
     * Calculate financial totals using decimal-safe rounding.
     *
     * @param array $items Array of ['quantity' => float, 'unit_price' => float]
     * @param float $discount Fixed discount amount
     * @param float $taxRate Decimal tax rate (e.g. 0.0825 for 8.25%)
     * @return array [subtotal, discount_amount, tax_rate, tax_amount, total, item_totals]
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
            'tax_rate'        => $taxRate,
            'tax_amount'      => $taxAmount,
            'total'           => $total,
            'item_totals'     => $itemTotals,
        ];
    }
}

