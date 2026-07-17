<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.manage');
    }

    public function rules(): array
    {
        return ['supplier_code' => ['required', 'max:50', Rule::unique('suppliers')->ignore($this->route('supplier'))], 'name' => 'required|max:150', 'company_name' => 'nullable|max:150', 'phone' => 'nullable|max:30', 'whatsapp' => 'nullable|max:30', 'email' => 'nullable|email|max:150', 'tax_number' => 'nullable|max:50', 'address' => 'nullable|max:2000', 'city' => 'nullable|max:100', 'province' => 'nullable|max:100', 'notes' => 'nullable|max:2000', 'is_active' => 'boolean'];
    }
}
