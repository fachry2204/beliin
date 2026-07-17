<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('products.view');
        $sort = in_array($request->sort, ['name', 'sku', 'selling_price'], true) ? $request->sort : 'name';
        $rows = Product::query()->with('category:id,name')
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->when($request->category_id, fn ($query, $id) => $query->where('category_id', $id))
            ->orderBy($sort, $request->direction === 'desc' ? 'desc' : 'asc')->paginate(15)->withQueryString();

        return Inertia::render('Masters/Index', [
            'title' => 'Data Barang', 'type' => 'product', 'rows' => $rows,
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canViewCost' => $request->user()->can('profit.view'),
        ]);
    }

    public function store(ProductRequest $request)
    {
        $model = Product::create($request->validated());
        $this->audit->record('create', 'product', $model, null, $model->toArray());

        return back()->with('success', 'Barang berhasil disimpan.');
    }

    public function update(ProductRequest $request, Product $product)
    {
        $old = $product->toArray();
        $product->update($request->validated());
        $this->audit->record('update', 'product', $product, $old, $product->fresh()->toArray());

        return back()->with('success', 'Barang diperbarui.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('products.manage');
        $product->delete();

        return back()->with('success', 'Barang dinonaktifkan.');
    }
}
