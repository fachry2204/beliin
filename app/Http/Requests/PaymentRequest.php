<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.manage');
    }

    public function rules(): array
    {
        return ['payment_date' => 'required|date', 'amount' => 'required|numeric|gt:0', 'payment_method' => 'required|in:transfer,cash,card,qris,virtual_account,other', 'bank_name' => 'nullable|max:100', 'reference_number' => 'nullable|max:150', 'notes' => 'nullable|max:2000', 'payment_proof' => 'nullable|file|max:4096|mimes:jpg,jpeg,png,pdf'];
    }
}
