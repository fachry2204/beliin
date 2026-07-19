<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\CombinedInvoiceDocument;
use App\Models\CombinedInvoiceSequence;
use App\Models\Courier;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CombinedInvoiceService
{
    public function __construct(private CashTransactionService $cash) {}

    public function create(Customer $customer, array $invoiceIds, ?string $dueDate, ?int $courierId, mixed $shippingCost, int $userId): CombinedInvoiceDocument
    {
        return DB::transaction(function () use ($customer, $invoiceIds, $dueDate, $courierId, $shippingCost, $userId) {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $courier = $courierId ? Courier::query()->where('is_active', true)->findOrFail($courierId) : null;
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
                'courier_id' => $courier?->id,
                'courier_name' => $courier?->name,
                'shipping_cost' => $shippingCost ?: 0,
                'status' => 'open',
                'opened_at' => $now,
                'due_date' => $dueDate,
            ]);

            $document->invoices()->attach($invoiceIds);
            $this->cash->syncFactureShipping($document, $userId);

            return $document;
        });
    }

    public function updateShipping(CombinedInvoiceDocument $document, ?int $courierId, mixed $shippingCost, int $userId): void
    {
        $courier = $courierId ? Courier::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $document->courier_id))
            ->findOrFail($courierId) : null;

        $document->update([
            'courier_id' => $courier?->id,
            'courier_name' => $courier?->name,
            'shipping_cost' => $shippingCost ?: 0,
        ]);
        $this->cash->syncFactureShipping($document->fresh(), $userId);
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
