<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\CombinedInvoiceDocument;
use App\Models\CombinedInvoiceSequence;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CombinedInvoiceService
{
    public function create(Customer $customer, array $invoiceIds, ?string $dueDate): CombinedInvoiceDocument
    {
        return DB::transaction(function () use ($customer, $invoiceIds, $dueDate) {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $now = now();
            CombinedInvoiceSequence::query()->insertOrIgnore([
                'year' => $now->year, 'month' => $now->month, 'last_number' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $sequence = CombinedInvoiceSequence::query()
                ->where(['year' => $now->year, 'month' => $now->month])
                ->lockForUpdate()
                ->firstOrFail();
            $sequence->increment('last_number');

            $document = CombinedInvoiceDocument::create([
                'facture_number' => sprintf('FKT/%04d/%02d/%05d', $now->year, $now->month, $sequence->last_number),
                'customer_id' => $customer->id,
                'status' => 'open',
                'opened_at' => $now,
                'due_date' => $dueDate,
            ]);

            $document->invoices()->attach($invoiceIds);

            return $document;
        });
    }

    public function closeIfSettled(Customer $customer): void
    {
        CombinedInvoiceDocument::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'open')
            ->get()
            ->each(fn (CombinedInvoiceDocument $document) => $this->refreshStatus($document));
    }

    public function refreshStatus(CombinedInvoiceDocument $document): void
    {
        $hasOutstanding = $document->invoices()->whereIn('status', [
            InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue,
        ])->where('remaining_amount', '>', 0)->exists();

        $document->update($hasOutstanding
            ? ['status' => 'open', 'closed_at' => null]
            : ['status' => 'closed', 'closed_at' => $document->closed_at ?? now()]);
    }
}
