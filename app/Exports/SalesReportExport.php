<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly bool $canViewProfit = false,
    ) {}

    public function collection()
    {
        return Invoice::with('customer:id,name,company_name')->when($this->from, fn ($q) => $q->whereDate('invoice_date', '>=', $this->from))->when($this->to, fn ($q) => $q->whereDate('invoice_date', '<=', $this->to))->latest('invoice_date')->get();
    }

    public function headings(): array
    {
        $headings = ['Nomor Invoice', 'Tanggal', 'Pelanggan', 'Subtotal', 'Diskon', 'Pajak', 'Grand Total'];

        if ($this->canViewProfit) {
            array_push($headings, 'Modal', 'Margin');
        }

        return [...$headings, 'Terbayar', 'Sisa', 'Status'];
    }

    public function map($invoice): array
    {
        $row = [$invoice->invoice_number, $invoice->invoice_date->format('Y-m-d'), $invoice->customer->company_name ?: $invoice->customer->name, $invoice->subtotal, $invoice->discount_amount, $invoice->tax_amount, $invoice->grand_total];

        if ($this->canViewProfit) {
            array_push($row, $invoice->total_cost, $invoice->gross_profit);
        }

        return [...$row, $invoice->paid_amount, $invoice->remaining_amount, $invoice->status->value];
    }
}
