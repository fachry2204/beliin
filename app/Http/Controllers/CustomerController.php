<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Services\AuditLogService;
use App\Services\CustomerCodeService;
use App\Services\CustomerItemPriceHistoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private CustomerCodeService $customerCodes,
        private CustomerItemPriceHistoryService $customerItemPrices,
    ) {}

    public function index(Request $r)
    {
        $this->authorize('customers.view');
        $rows = Customer::query()->withCount('invoices')->when($r->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('customer_code', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%")))->orderBy($r->sort ?? 'name', $r->direction === 'desc' ? 'desc' : 'asc')->paginate(15)->withQueryString();

        return Inertia::render('Masters/Index', ['title' => 'Data Pelanggan', 'type' => 'customer', 'rows' => $rows]);
    }

    public function store(CustomerRequest $r)
    {
        $data = $r->validated();
        $m = $this->customerCodes->create($data);
        $this->audit->record('create', 'customer', $m, null, $m->toArray());

        return back()->with('success', 'Pelanggan berhasil disimpan.');
    }

    public function update(CustomerRequest $r, Customer $customer)
    {
        $old = $customer->toArray();
        $customer->update($r->validated());
        $this->audit->record('update', 'customer', $customer, $old, $customer->fresh()->toArray());

        return back()->with('success', 'Pelanggan diperbarui.');
    }

    public function itemPrices(Request $request, Customer $customer)
    {
        $this->authorize('invoices.create');

        return response()->json([
            'items' => $this->customerItemPrices->latestForCustomer(
                $customer,
                $request->user()->can('profit.view'),
            ),
        ]);
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('customers.manage');
        if ($customer->invoices()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Client tidak dapat dihapus karena masih memiliki data invoice. Hapus seluruh invoice client terlebih dahulu.',
            ]);
        }
        $customer->delete();

        return back()->with('success', 'Pelanggan dinonaktifkan.');
    }
}
