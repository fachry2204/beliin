<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IncomingGoodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('incoming.manage');
    }

    public function rules(): array
    {
        return ['supplier_id' => 'required|exists:suppliers,id', 'transaction_date' => 'required|date', 'supplier_invoice_number' => 'nullable|max:100', 'purchase_order_number' => 'nullable|max:100', 'notes' => 'nullable|max:3000', 'items' => 'required|array|min:1', 'items.*.product_id' => 'required|exists:products,id', 'items.*.purchase_price' => 'required|numeric|gt:0', 'items.*.quantity' => 'required|numeric|gt:0', 'items.*.volume' => 'required|numeric|gt:0', 'items.*.calculation_method' => 'required|in:qty,qty_volume'];
    }
}
