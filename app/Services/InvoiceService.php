<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\CashTransaction;
use App\Models\CompanySetting;
use App\Models\Courier;
use App\Models\CourierDelivery;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private InvoiceCalculationService $calculator,
        private InvoiceNumberService $numbers,
        private AuditLogService $audit,
        private CashTransactionService $cash,
        private CombinedInvoiceService $combinedInvoices,
        private CourierPushService $courierPush,
        private CustomerItemPriceHistoryService $customerItemPrices,
    ) {}

    public function create(array $data, int $userId): Invoice
    {
        return DB::transaction(function () use ($data, $userId) {
            $data = $this->applyCompanyFeatureSettings($data);
            $customer = Customer::findOrFail($data['customer_id']);
            $courier = filled($data['courier_id'] ?? null) ? Courier::findOrFail($data['courier_id']) : null;
            $rows = [];
            foreach ($data['items'] as $index => $row) {
                $product = filled($row['product_id'] ?? null) ? Product::findOrFail($row['product_id']) : null;
                $purchasePrice = isset($row['purchase_price']) && $row['purchase_price'] !== ''
                    ? $row['purchase_price']
                    : ($product?->average_purchase_price ?? 0);
                $rows[] = array_merge($row, [
                    'product_id' => $product?->id,
                    'volume' => 1,
                    'calculation_method' => 'qty',
                    'purchase_price' => $purchasePrice,
                    'product_name_snapshot' => $product?->name ?? $row['product_name'],
                    'sku_snapshot' => $product?->sku ?? (($row['sku'] ?? null) ?: 'MANUAL'),
                    'unit_snapshot' => $row['unit'] ?? $product?->unit ?? 'Pcs',
                ]);
            }
            $data['items'] = $rows;
            $calculation = $this->calculator->calculate($data);
            $invoice = Invoice::create(array_merge($data, $calculation, [
                'invoice_number' => $this->numbers->next(Carbon::parse($data['invoice_date'])),
                'billing_name' => $customer->name, 'billing_company' => $customer->company_name,
                'billing_phone' => $customer->phone, 'billing_email' => $customer->email,
                'billing_address' => $customer->address, 'paid_amount' => '0',
                'courier_id' => $courier?->id, 'courier_name' => $courier?->name,
                'remaining_amount' => $calculation['grand_total'], 'status' => InvoiceStatus::Draft,
                'created_by' => $userId,
            ]));
            foreach ($calculation['items'] as $row) {
                $invoice->items()->create($row);
            }
            $this->customerItemPrices->recordInvoice($invoice);
            $this->audit->record('create', 'invoice', $invoice, null, $invoice->fresh()->toArray());
            Cache::forget('dashboard.metrics');

            return $invoice->fresh(['customer', 'items']);
        });
    }

    public function updateDraft(Invoice $invoice, array $data, int $userId): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            $data = $this->applyCompanyFeatureSettings($data);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_if($invoice->status === InvoiceStatus::Cancelled, 422, 'Invoice yang dibatalkan tidak dapat diubah.');
            $previousStatus = $invoice->status;
            $customer = Customer::findOrFail($data['customer_id']);
            if ($previousStatus === InvoiceStatus::Draft) {
                $courier = filled($data['courier_id'] ?? null) ? Courier::findOrFail($data['courier_id']) : null;
            } else {
                $courier = $invoice->courier;
                $data['courier_id'] = $invoice->courier_id;
                $data['shipping_cost'] = $invoice->shipping_cost;
            }
            $rows = [];
            foreach ($data['items'] as $index => $row) {
                $product = filled($row['product_id'] ?? null) ? Product::findOrFail($row['product_id']) : null;
                $purchasePrice = isset($row['purchase_price']) && $row['purchase_price'] !== '' ? $row['purchase_price'] : ($product?->average_purchase_price ?? 0);
                $rows[] = array_merge($row, ['product_id' => $product?->id, 'volume' => 1, 'calculation_method' => 'qty', 'purchase_price' => $purchasePrice, 'product_name_snapshot' => $product?->name ?? $row['product_name'], 'sku_snapshot' => $product?->sku ?? (($row['sku'] ?? null) ?: 'MANUAL'), 'unit_snapshot' => $row['unit'] ?? $product?->unit ?? 'Pcs']);
            }
            $old = $invoice->load('items')->toArray();
            $data['items'] = $rows;
            $calculation = $this->calculator->calculate($data);
            $paidAmount = (string) $invoice->paid_amount;
            if (bccomp($calculation['grand_total'], $paidAmount, 2) < 0) {
                throw ValidationException::withMessages(['items' => 'Grand Total baru tidak boleh lebih kecil dari jumlah yang sudah dibayar.']);
            }
            $remainingAmount = bcsub($calculation['grand_total'], $paidAmount, 2);
            $status = $this->statusAfterEdit($previousStatus, $paidAmount, $remainingAmount, $data['due_date']);
            $invoice->update(array_merge($data, $calculation, ['courier_id' => $courier?->id, 'courier_name' => $courier?->name, 'billing_name' => $customer->name, 'billing_company' => $customer->company_name, 'billing_phone' => $customer->phone, 'billing_email' => $customer->email, 'billing_address' => $customer->address, 'remaining_amount' => $remainingAmount, 'status' => $status]));
            $invoice->items()->delete();
            foreach ($calculation['items'] as $row) {
                $invoice->items()->create($row);
            }
            $invoice->unsetRelation('items');
            $this->customerItemPrices->recordInvoice($invoice);
            if ($invoice->status !== InvoiceStatus::Draft) {
                $this->syncCourierDelivery($invoice);
                $this->cash->syncInvoiceShipping($invoice, $userId);
                $this->cash->syncInvoiceCost($invoice, $userId);
            }
            $this->audit->record('update', 'invoice', $invoice, $old, $invoice->fresh('items')->toArray());
            Cache::forget('dashboard.metrics');

            return $invoice->fresh(['customer', 'items']);
        });
    }

    public function issue(Invoice $invoice, int $userId, bool $shippingPaidNow, ?int $courierId = null, mixed $shippingCost = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $userId, $shippingPaidNow, $courierId, $shippingCost) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->status === InvoiceStatus::Draft, 422, 'Hanya draft yang dapat diterbitkan.');
            $courier = $courierId ? Courier::where('is_active', true)->findOrFail($courierId) : $invoice->courier;
            $shippingCost = $shippingCost ?? $invoice->shipping_cost;
            if (bccomp((string) $shippingCost, '0', 2) > 0 && ! $courier) {
                throw ValidationException::withMessages([
                    'courier_id' => 'Pilih kurir terlebih dahulu sebelum menerbitkan invoice dengan ongkir.',
                ]);
            }
            $invoice->update([
                'courier_id' => $courier?->id,
                'courier_name' => $courier?->name,
                'shipping_cost' => $shippingCost,
                'status' => InvoiceStatus::Unpaid,
                'issued_at' => now(),
            ]);
            $delivery = $this->syncCourierDelivery($invoice);
            $this->cash->syncInvoiceShipping($invoice, $userId, $shippingPaidNow);
            $this->cash->syncInvoiceCost($invoice, $userId);
            $this->audit->record('issue', 'invoice', $invoice);
            Cache::forget('dashboard.metrics');

            if ($delivery) {
                DB::afterCommit(fn () => $this->courierPush->sendNewTask($delivery));
            }

            return $invoice->fresh();
        });
    }

    public function updateShipping(Invoice $invoice, int $userId, int $courierId, mixed $shippingCost, bool $shippingPaidNow, ?string $deliveryStatus = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $userId, $courierId, $shippingCost, $shippingPaidNow, $deliveryStatus) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_if($invoice->status === InvoiceStatus::Draft, 422, 'Invoice draft menggunakan pengaturan pengiriman saat diterbitkan.');
            abort_if($invoice->status === InvoiceStatus::Cancelled, 422, 'Pengiriman invoice yang dibatalkan tidak dapat diubah.');

            $courier = Courier::withTrashed()
                ->whereKey($courierId)
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $invoice->courier_id))
                ->firstOrFail();
            $old = $invoice->load(['shippingDeposit', 'delivery'])->toArray();
            $previousCourierId = $invoice->courier_id;

            $invoice->update([
                'courier_id' => $courier->id,
                'courier_name' => $courier->name,
                'shipping_cost' => $shippingCost,
            ]);

            $delivery = $this->syncCourierDelivery($invoice, $deliveryStatus);
            if ($delivery && $deliveryStatus !== null) {
                $this->applyDeliveryStatus($delivery, $deliveryStatus);
            }
            $this->cash->syncInvoiceShipping($invoice, $userId, $shippingPaidNow);
            $this->audit->record('update_shipping', 'invoice', $invoice, $old, $invoice->fresh(['shippingDeposit', 'delivery'])->toArray());
            Cache::forget('dashboard.metrics');

            if ($delivery && $previousCourierId !== $courier->id) {
                DB::afterCommit(fn () => $this->courierPush->sendNewTask($delivery));
            }

            return $invoice->fresh(['shippingDeposit', 'delivery']);
        });
    }

    public function cancel(Invoice $invoice, int $userId): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_if($invoice->status === InvoiceStatus::Cancelled, 422, 'Invoice sudah dibatalkan.');
            abort_if($reason = $this->destructiveLockReason($invoice), 422, $reason);
            $old = $invoice->load('payments')->toArray();
            $this->deletePaymentHistory($invoice);
            $this->cash->deleteInvoiceShipping($invoice);
            $this->cash->deleteInvoiceCost($invoice);
            $invoice->delivery()->update(['status' => CourierDelivery::CANCELLED]);
            $invoice->update([
                'status' => InvoiceStatus::Cancelled,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'cancelled_at' => now(),
            ]);
            $this->combinedInvoices->closeIfSettled($invoice->customer);
            $this->audit->record('cancel', 'invoice', $invoice, $old, $invoice->fresh()->toArray());
            Cache::forget('dashboard.metrics');

            return $invoice->fresh();
        });
    }

    public function deleteInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_if($reason = $this->destructiveLockReason($invoice), 422, $reason);
            $old = $invoice->load('payments')->toArray();
            $this->deletePaymentHistory($invoice);
            $this->cash->deleteInvoiceShipping($invoice);
            $this->cash->deleteInvoiceCost($invoice);
            $this->audit->record('delete', 'invoice', $invoice, $old);
            $invoice->delete();
            $this->combinedInvoices->closeIfSettled($invoice->customer);
            Cache::forget('dashboard.metrics');
        });
    }

    public function destructiveLockReason(Invoice $invoice): ?string
    {
        $reasons = [];
        $deliveryStarted = $invoice->delivery()
            ->whereIn('status', [
                CourierDelivery::ACCEPTED,
                CourierDelivery::IN_TRANSIT,
                CourierDelivery::DELIVERED,
            ])->exists();

        if ($deliveryStarted) {
            $reasons[] = 'kurir sudah mengambil atau menjalankan tugas pengiriman';
        }

        $factureNumbers = $invoice->combinedDocuments()->pluck('facture_number');
        if ($factureNumbers->isNotEmpty()) {
            $reasons[] = 'invoice sudah masuk Faktur '.$factureNumbers->join(', ');
        }

        return $reasons === []
            ? null
            : 'Invoice tidak dapat dibatalkan atau dihapus karena '.implode(' dan ', $reasons).'. Selesaikan atau lepaskan keterkaitan data tersebut terlebih dahulu.';
    }

    private function deletePaymentHistory(Invoice $invoice): void
    {
        $paymentIds = $invoice->payments()->pluck('id');

        if ($paymentIds->isEmpty()) {
            return;
        }

        CashTransaction::withTrashed()
            ->whereIn('payment_id', $paymentIds)
            ->forceDelete();

        $invoice->payments()->delete();
        $invoice->unsetRelation('payments');
    }

    private function applyCompanyFeatureSettings(array $data): array
    {
        $settings = CompanySetting::first(['tax_enabled', 'discount_enabled']);

        if ($settings && ! $settings->discount_enabled) {
            $data['discount_type'] = 'nominal';
            $data['discount_value'] = 0;
        }

        if ($settings && ! $settings->tax_enabled) {
            $data['tax_percentage'] = 0;
        }

        return $data;
    }

    private function statusAfterEdit(InvoiceStatus $previousStatus, string $paidAmount, string $remainingAmount, string $dueDate): InvoiceStatus
    {
        if ($previousStatus === InvoiceStatus::Draft) {
            return InvoiceStatus::Draft;
        }
        if (bccomp($remainingAmount, '0', 2) === 0) {
            return InvoiceStatus::Paid;
        }
        if (Carbon::parse($dueDate)->isBefore(today())) {
            return InvoiceStatus::Overdue;
        }

        return bccomp($paidAmount, '0', 2) > 0 ? InvoiceStatus::PartiallyPaid : InvoiceStatus::Unpaid;
    }

    private function syncCourierDelivery(Invoice $invoice, ?string $requestedStatus = null): ?CourierDelivery
    {
        if (! $invoice->courier_id) {
            $invoice->delivery()->where('status', CourierDelivery::PENDING)->delete();

            return null;
        }

        $delivery = $invoice->delivery()->first();
        if ($delivery && $delivery->courier_id !== $invoice->courier_id && $delivery->status !== CourierDelivery::PENDING && $requestedStatus !== CourierDelivery::PENDING) {
            throw ValidationException::withMessages(['courier_id' => 'Kurir tidak dapat diganti setelah tugas diambil.']);
        }

        return CourierDelivery::updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'courier_id' => $invoice->courier_id,
                'status' => $requestedStatus ?? $delivery?->status ?? CourierDelivery::PENDING,
            ],
        );
    }

    private function applyDeliveryStatus(CourierDelivery $delivery, string $status): void
    {
        $now = now();
        $updates = ['status' => $status];
        $filesToDelete = [];

        if ($status === CourierDelivery::PENDING) {
            $filesToDelete = array_filter([$delivery->departure_photo_path, $delivery->proof_photo_path]);
            $updates = array_merge($updates, [
                'accepted_at' => null,
                'departed_at' => null,
                'delivered_at' => null,
                'accepted_latitude' => null,
                'accepted_longitude' => null,
                'departed_latitude' => null,
                'departed_longitude' => null,
                'departed_accuracy' => null,
                'departure_address' => null,
                'departure_photo_path' => null,
                'departure_photo_taken_at' => null,
                'delivered_latitude' => null,
                'delivered_longitude' => null,
                'delivered_accuracy' => null,
                'delivery_address' => null,
                'proof_photo_path' => null,
                'proof_taken_at' => null,
                'delivery_notes' => null,
            ]);
        } elseif ($status === CourierDelivery::ACCEPTED) {
            $filesToDelete = array_filter([$delivery->departure_photo_path, $delivery->proof_photo_path]);
            $updates = array_merge($updates, [
                'accepted_at' => $delivery->accepted_at ?? $now,
                'departed_at' => null,
                'delivered_at' => null,
                'departed_latitude' => null,
                'departed_longitude' => null,
                'departed_accuracy' => null,
                'departure_address' => null,
                'departure_photo_path' => null,
                'departure_photo_taken_at' => null,
                'delivered_latitude' => null,
                'delivered_longitude' => null,
                'delivered_accuracy' => null,
                'delivery_address' => null,
                'proof_photo_path' => null,
                'proof_taken_at' => null,
                'delivery_notes' => null,
            ]);
        } elseif ($status === CourierDelivery::IN_TRANSIT) {
            $filesToDelete = array_filter([$delivery->proof_photo_path]);
            $updates = array_merge($updates, [
                'accepted_at' => $delivery->accepted_at ?? $now,
                'departed_at' => $delivery->departed_at ?? $now,
                'delivered_at' => null,
                'delivered_latitude' => null,
                'delivered_longitude' => null,
                'delivered_accuracy' => null,
                'delivery_address' => null,
                'proof_photo_path' => null,
                'proof_taken_at' => null,
                'delivery_notes' => null,
            ]);
        } elseif ($status === CourierDelivery::DELIVERED) {
            $updates = array_merge($updates, [
                'accepted_at' => $delivery->accepted_at ?? $now,
                'departed_at' => $delivery->departed_at ?? $now,
                'delivered_at' => $delivery->delivered_at ?? $now,
            ]);
        }

        $delivery->update($updates);

        if ($filesToDelete !== []) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($filesToDelete));
        }
    }
}
