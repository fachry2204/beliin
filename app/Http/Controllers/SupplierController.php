<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function index(Request $r)
    {
        $this->authorize('suppliers.view');
        $rows = Supplier::query()->when($r->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('supplier_code', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%")))->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Masters/Index', ['title' => 'Data Supplier', 'type' => 'supplier', 'rows' => $rows]);
    }

    public function store(SupplierRequest $r)
    {
        $m = Supplier::create($r->validated());
        $this->audit->record('create', 'supplier', $m);

        return back()->with('success', 'Supplier disimpan.');
    }

    public function update(SupplierRequest $r, Supplier $supplier)
    {
        $old = $supplier->toArray();
        $supplier->update($r->validated());
        $this->audit->record('update', 'supplier', $supplier, $old, $supplier->fresh()->toArray());

        return back()->with('success', 'Supplier diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('suppliers.manage');
        $supplier->delete();

        return back();
    }
}
