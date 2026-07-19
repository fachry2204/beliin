<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.manage');
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return ['customer_code' => [$customer ? 'required' : 'nullable', 'max:50', Rule::unique('customers')->ignore($customer)], 'name' => 'required|max:150', 'company_name' => 'nullable|max:150', 'phone' => 'nullable|max:30', 'whatsapp' => 'nullable|max:30', 'email' => 'nullable|email|max:150', 'tax_number' => 'nullable|max:50', 'address' => 'nullable|max:2000', 'city' => 'nullable|max:100', 'province' => 'nullable|max:100', 'postal_code' => 'nullable|max:10', 'notes' => 'nullable|max:2000', 'is_active' => 'boolean'];
    }
}
