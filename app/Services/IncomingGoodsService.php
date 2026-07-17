<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\IncomingTransaction;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IncomingGoodsService
{
    public function __construct(private InventoryService $inventory, private AuditLogService $audit) {}

    public function create(array $data, int $userId): IncomingTransaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $transaction = IncomingTransaction::create(array_merge($data, ['transaction_number' => 'BM/'.now()->format('Y/m').'/'.strtoupper(substr((string) str()->uuid(), 0, 8)), 'subtotal' => '0', 'status' => TransactionStatus::Draft, 'created_by' => $userId]));
            $subtotal = '0';
            foreach ($data['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $factor = $row['calculation_method'] === 'qty_volume' ? bcmul((string) $row['quantity'], (string) $row['volume'], 4) : (string) $row['quantity'];
                $line = bcmul((string) $row['purchase_price'], $factor, 2);
                $subtotal = bcadd($subtotal, $line, 2);
                $transaction->items()->create(array_merge($row, ['product_name_snapshot' => $product->name, 'unit' => $product->unit, 'line_total' => $line]));
            }
            $transaction->update(['subtotal' => $subtotal]);
            $this->audit->record('create', 'incoming_goods', $transaction, null, $transaction->fresh()->toArray());

            return $transaction;
        });
    }

    public function finalize(IncomingTransaction $transaction, int $userId): IncomingTransaction
    {
        return DB::transaction(function () use ($transaction, $userId) {
            $transaction = IncomingTransaction::query()->with('items.product')->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($transaction->status === TransactionStatus::Draft, 422, 'Transaksi sudah dikunci.');
            foreach ($transaction->items as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
                $effective = $item->calculation_method === 'qty_volume' ? bcmul((string) $item->quantity, (string) $item->volume, 4) : (string) $item->quantity;
                $oldValue = bcmul((string) $product->stock, (string) $product->average_purchase_price, 4);
                $newStock = bcadd((string) $product->stock, $effective, 4);
                $average = bccomp($newStock, '0', 4) > 0 ? bcdiv(bcadd($oldValue, bcmul($effective, (string) $item->purchase_price, 4), 4), $newStock, 2) : (string) $item->purchase_price;
                $this->inventory->move($product, 'IN', $effective, $transaction, $userId, 'Finalisasi barang masuk');
                $product->update(['purchase_price' => $item->purchase_price, 'average_purchase_price' => $average]);
            }
            $transaction->update(['status' => TransactionStatus::Final, 'finalized_at' => now()]);
            Cache::forget('dashboard.metrics');
            $this->audit->record('finalize', 'incoming_goods', $transaction);

            return $transaction->fresh('items');
        });
    }
}
