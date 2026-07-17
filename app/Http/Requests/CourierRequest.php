<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('couriers.manage');
    }

    public function rules(): array
    {
        return [
            'courier_code' => ['required', 'max:50', Rule::unique('couriers')->ignore($this->route('courier'))],
            'name' => 'required|max:150',
            'phone' => 'nullable|max:30',
            'vehicle_type' => 'nullable|max:100',
            'license_plate' => 'nullable|max:20',
            'bank_name' => 'nullable|max:100',
            'bank_account_number' => 'nullable|max:100',
            'bank_account_name' => 'nullable|max:150',
            'notes' => 'nullable|max:2000',
            'is_active' => 'boolean',
        ];
    }
}
