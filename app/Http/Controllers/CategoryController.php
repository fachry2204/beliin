<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('products.view');
        $rows = ProductCategory::query()
            ->withCount('products')
            ->when($request->search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Masters/Index', ['title' => 'Kategori Barang', 'type' => 'category', 'rows' => $rows]);
    }

    public function store(Request $request)
    {
        $this->authorize('products.manage');
        $data = $request->validate(['name' => 'required|max:150|unique:product_categories', 'description' => 'nullable|max:2000', 'is_active' => 'boolean']);
        $model = ProductCategory::create($data);
        $this->audit->record('create', 'category', $model, null, $model->toArray());

        return back()->with('success', 'Kategori berhasil disimpan.');
    }

    public function update(Request $request, ProductCategory $category)
    {
        $this->authorize('products.manage');
        $data = $request->validate(['name' => 'required|max:150|unique:product_categories,name,'.$category->id, 'description' => 'nullable|max:2000', 'is_active' => 'boolean']);
        $old = $category->toArray();
        $category->update($data);
        $this->audit->record('update', 'category', $category, $old, $category->fresh()->toArray());

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroy(ProductCategory $category)
    {
        $this->authorize('products.manage');
        abort_if($category->products()->exists(), 422, 'Kategori masih digunakan.');
        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }
}
