<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                return array_merge($item, [
                    'purchase_price' => $this->user()->can('profit.view') ? ($item['purchase_price'] ?? null) : null,
                    'volume' => 1,
                    'calculation_method' => 'qty',
                ]);
            })
            ->all();

        $shippingCost = $this->input('shipping_cost');

        $this->merge([
            'items' => $items,
            'shipping_cost' => blank($shippingCost) ? 0 : $shippingCost,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('invoices.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id', 'courier_id' => 'nullable|exists:couriers,id', 'invoice_date' => 'required|date', 'due_date' => 'required|date|after_or_equal:invoice_date', 'purchase_order_number' => 'nullable|max:100',
            'discount_type' => 'required|in:percentage,nominal',
            'discount_value' => ['required', 'numeric', 'min:0', Rule::when($this->input('discount_type') === 'percentage', ['integer', 'max:100'])],
            'tax_percentage' => 'required|integer|min:0|max:100',
            'shipping_cost' => 'nullable|numeric|min:0', 'notes' => 'nullable|max:3000', 'terms' => 'nullable|max:3000', 'items' => 'required|array|min:1', 'items.*.product_id' => 'nullable|exists:products,id', 'items.*.product_name' => 'required|string|max:255', 'items.*.sku' => 'nullable|string|max:100', 'items.*.unit' => 'required|string|max:30', 'items.*.purchase_price' => 'nullable|numeric|min:0', 'items.*.selling_price' => 'required|numeric|gt:0', 'items.*.quantity' => 'required|numeric|gt:0', 'items.*.volume' => 'required|numeric|in:1', 'items.*.calculation_method' => 'required|in:qty',
        ];
    }
}
