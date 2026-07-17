<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.manage');
    }

    public function rules(): array
    {
        return ['category_id' => 'required|exists:product_categories,id', 'sku' => ['required', 'max:80', Rule::unique('products')->ignore($this->route('product'))], 'barcode' => ['nullable', 'max:80', Rule::unique('products')->ignore($this->route('product'))], 'name' => 'required|max:200', 'description' => 'nullable|max:3000', 'unit' => 'required|max:30', 'purchase_price' => 'required|numeric|min:0', 'selling_price' => 'required|numeric|min:0', 'minimum_stock' => 'required|numeric|min:0', 'is_active' => 'boolean'];
    }
}
