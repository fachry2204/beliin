<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cash.manage');
    }

    public function rules(): array
    {
        return [
            'transaction_date' => 'required|date',
            'category' => 'required|max:100',
            'description' => 'required|max:255',
            'payment_method' => 'required|in:cash,transfer,card,qris,other',
            'amount' => 'required|numeric|gt:0|max:999999999999999999',
            'reference_number' => 'nullable|max:150',
            'notes' => 'nullable|max:2000',
        ];
    }
}
