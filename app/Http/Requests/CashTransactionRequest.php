<?php

namespace App\Http\Requests;

use App\Models\CashTransaction;
use App\Models\CompanySetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cash.manage');
    }

    public function rules(): array
    {
        $categoryRules = ['required', 'string', 'max:100'];

        if ($this->routeIs('cash-in.*') || $this->routeIs('cash-out.*')) {
            $cashType = $this->routeIs('cash-in.*') ? 'in' : 'out';
            $allowedCategories = $cashType === 'in'
                ? CompanySetting::availableCashInCategories()
                : CompanySetting::availableCashOutCategories();
            $transaction = $this->route('cashTransaction');
            if ($transaction instanceof CashTransaction && $transaction->type === $cashType) {
                $allowedCategories[] = $transaction->category;
            }
            $categoryRules[] = Rule::in(array_values(array_unique($allowedCategories)));
        }

        return [
            'transaction_date' => 'required|date',
            'category' => $categoryRules,
            'description' => 'required|max:255',
            'payment_method' => 'required|in:cash,transfer,card,qris,other',
            'amount' => 'required|numeric|gt:0|max:999999999999999999',
            'reference_number' => 'nullable|max:150',
            'notes' => 'nullable|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => $this->routeIs('cash-in.*')
                ? 'Kategori Kas Masuk harus dipilih dari daftar di Pengaturan Perusahaan.'
                : 'Kategori Kas Keluar harus dipilih dari daftar di Pengaturan Perusahaan.',
        ];
    }
}
