<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomingGoodsRequest;
use App\Models\IncomingTransaction;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\IncomingGoodsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IncomingTransactionController extends Controller
{
    public function __construct(private IncomingGoodsService $service) {}

    public function index(Request $request)
    {
        $this->authorize('incoming.view');
        $rows = IncomingTransaction::query()->with(['supplier:id,name,company_name', 'creator:id,name'])
            ->when($request->search, fn ($query, $search) => $query->where('transaction_number', 'like', "%{$search}%"))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest('transaction_date')->paginate(15)->withQueryString();

        return Inertia::render('Incoming/Index', ['rows' => $rows]);
    }

    public function create()
    {
        $this->authorize('incoming.manage');

        return Inertia::render('Incoming/Create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'company_name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'purchase_price']),
        ]);
    }

    public function store(IncomingGoodsRequest $request)
    {
        $transaction = $this->service->create($request->validated(), $request->user()->id);

        return redirect()->route('incoming.index')->with('success', "Barang masuk {$transaction->transaction_number} disimpan sebagai draft.");
    }

    public function finalize(Request $request, IncomingTransaction $incomingTransaction)
    {
        $this->authorize('incoming.manage');
        $this->service->finalize($incomingTransaction, $request->user()->id);

        return back()->with('success', 'Barang masuk difinalisasi dan stok telah diperbarui.');
    }
}
