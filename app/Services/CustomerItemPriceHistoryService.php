<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerItemPriceHistory;
use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerItemPriceHistoryService
{
    public function recordInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('items');
        CustomerItemPriceHistory::where('invoice_id', $invoice->id)->delete();

        foreach ($invoice->items as $lineNumber => $item) {
            CustomerItemPriceHistory::create([
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'line_number' => $lineNumber + 1,
                'item_key' => $this->itemKey($item->product_id, $item->product_name_snapshot),
                'product_name' => $item->product_name_snapshot,
                'sku' => $item->sku_snapshot,
                'unit' => $item->unit_snapshot,
                'purchase_price' => $item->purchase_price,
                'selling_price' => $item->selling_price,
                'invoice_date' => $invoice->invoice_date,
                'recorded_at' => now(),
            ]);
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    public function latestForCustomer(Customer $customer, bool $includeCost): Collection
    {
        return CustomerItemPriceHistory::query()
            ->where('customer_id', $customer->id)
            ->whereHas('invoice', fn ($query) => $query->where('status', '!=', InvoiceStatus::Cancelled->value))
            ->with('invoice:id,invoice_number,invoice_date')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get()
            ->unique('item_key')
            ->values()
            ->map(fn (CustomerItemPriceHistory $history) => [
                'product_id' => $history->product_id,
                'name' => $history->product_name,
                'sku' => $history->sku,
                'unit' => $history->unit,
                'purchase_price' => $includeCost ? $history->purchase_price : null,
                'selling_price' => $history->selling_price,
                'invoice_number' => $history->invoice?->invoice_number,
                'invoice_date' => $history->invoice_date?->toDateString(),
            ]);
    }

    private function itemKey(?int $productId, string $productName): string
    {
        if ($productId) {
            return 'product:'.$productId;
        }

        return 'manual:'.sha1(Str::lower(Str::squish($productName)));
    }
}
