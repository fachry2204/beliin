<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class InvoiceCalculationService
{
    public function calculate(array $data): array
    {
        $subtotal = '0.00';
        $totalCost = '0.00';
        $items = [];
        foreach ($data['items'] as $item) {
            $factor = (string) $item['quantity'];
            $hasSellingTotal = array_key_exists('selling_total', $item) && $item['selling_total'] !== '' && $item['selling_total'] !== null;
            $hasPurchaseTotal = array_key_exists('purchase_total', $item) && $item['purchase_total'] !== '' && $item['purchase_total'] !== null;
            $line = $hasSellingTotal
                ? bcadd((string) $item['selling_total'], '0', 2)
                : bcmul((string) $item['selling_price'], $factor, 2);
            $cost = $hasPurchaseTotal
                ? bcadd((string) $item['purchase_total'], '0', 2)
                : bcmul((string) $item['purchase_price'], $factor, 2);
            $sellingPrice = $hasSellingTotal ? bcdiv($line, $factor, 2) : (string) $item['selling_price'];
            $purchasePrice = $hasPurchaseTotal ? bcdiv($cost, $factor, 2) : (string) $item['purchase_price'];
            $subtotal = bcadd($subtotal, $line, 2);
            $totalCost = bcadd($totalCost, $cost, 2);
            $items[] = array_merge($item, [
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'line_subtotal' => $line,
                'cost_total' => $cost,
                'profit' => bcsub($line, $cost, 2),
            ]);
        }
        $discountType = $data['discount_type'] ?? 'nominal';
        $discountValue = (string) ($data['discount_value'] ?? 0);
        $discount = $discountType === 'percentage' ? bcdiv(bcmul($subtotal, $discountValue, 4), '100', 2) : $discountValue;
        if (bccomp($discount, $subtotal, 2) > 0) {
            throw ValidationException::withMessages(['discount_value' => 'Diskon tidak boleh melebihi subtotal.']);
        }
        $taxBase = bcsub($subtotal, $discount, 2);
        $tax = bcdiv(bcmul($taxBase, (string) ($data['tax_percentage'] ?? 0), 4), '100', 2);
        $grand = bcadd($taxBase, $tax, 2);
        if (bccomp($grand, '0', 2) < 0) {
            throw ValidationException::withMessages(['grand_total' => 'Grand total tidak boleh negatif.']);
        }

        return ['items' => $items, 'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_base' => $taxBase, 'tax_amount' => $tax, 'grand_total' => $grand, 'total_cost' => $totalCost, 'gross_profit' => bcsub($taxBase, $totalCost, 2)];
    }
}
