<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function move(Product $product, string $type, string $quantity, object $reference, int $userId, ?string $notes = null): void
    {
        $product = Product::query()->lockForUpdate()->findOrFail($product->id);
        $before = (string) $product->stock;
        $after = $type === 'IN' ? bcadd($before, $quantity, 4) : bcsub($before, $quantity, 4);
        if (bccomp($after, '0', 4) < 0) {
            throw ValidationException::withMessages(['items' => 'Stok '.$product->name.' tidak mencukupi.']);
        }
        $product->update(['stock' => $after]);
        StockMovement::create(['product_id' => $product->id, 'reference_type' => $reference::class, 'reference_id' => $reference->id, 'movement_type' => $type, 'quantity' => $quantity, 'stock_before' => $before, 'stock_after' => $after, 'notes' => $notes, 'created_by' => $userId]);
    }
}
