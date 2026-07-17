<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashTransactionRequest;
use App\Models\CashTransaction;
use App\Services\CashTransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashTransactionController extends Controller
{
    public function __construct(private CashTransactionService $service) {}

    public function incoming(Request $request)
    {
        return $this->index($request, 'in');
    }

    public function outgoing(Request $request)
    {
        return $this->index($request, 'out');
    }

    public function storeIncoming(CashTransactionRequest $request)
    {
        return $this->store($request, 'in');
    }

    public function storeOutgoing(CashTransactionRequest $request)
    {
        return $this->store($request, 'out');
    }

    public function updateIncoming(CashTransactionRequest $request, CashTransaction $cashTransaction)
    {
        return $this->updateTransaction($request, $cashTransaction, 'in');
    }

    public function updateOutgoing(CashTransactionRequest $request, CashTransaction $cashTransaction)
    {
        return $this->updateTransaction($request, $cashTransaction, 'out');
    }

    public function destroyIncoming(CashTransaction $cashTransaction)
    {
        return $this->destroyTransaction($cashTransaction, 'in');
    }

    public function destroyOutgoing(CashTransaction $cashTransaction)
    {
        return $this->destroyTransaction($cashTransaction, 'out');
    }

    private function index(Request $request, string $type)
    {
        $this->authorize('cash.view');
        $rows = CashTransaction::query()
            ->with('creator:id,name')
            ->withExists('factureCommission')
            ->where('type', $type)
            ->when($request->search, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('transaction_number', 'like', "%$search%")
                ->orWhere('category', 'like', "%$search%")
                ->orWhere('description', 'like', "%$search%")
                ->orWhere('reference_number', 'like', "%$search%")))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();
        $incoming = (float) CashTransaction::where('type', 'in')->sum('amount');
        $outgoing = (float) CashTransaction::where('type', 'out')->sum('amount');

        return Inertia::render('Cash/Index', [
            'type' => $type,
            'rows' => $rows,
            'typeTotal' => $type === 'in' ? $incoming : $outgoing,
            'cashBalance' => $incoming - $outgoing,
        ]);
    }

    private function store(CashTransactionRequest $request, string $type)
    {
        $this->service->create($type, $request->validated(), $request->user()->id);

        return back()->with('success', $type === 'in' ? 'Cash masuk berhasil dicatat.' : 'Cash keluar berhasil dicatat.');
    }

    private function updateTransaction(CashTransactionRequest $request, CashTransaction $cashTransaction, string $type)
    {
        abort_unless($cashTransaction->type === $type, 404);
        $this->service->update($cashTransaction, $request->validated(), $request->user()->id);

        return back()->with('success', 'Transaksi kas berhasil diperbarui.');
    }

    private function destroyTransaction(CashTransaction $cashTransaction, string $type)
    {
        $this->authorize('cash.manage');
        abort_unless($cashTransaction->type === $type, 404);
        $this->service->delete($cashTransaction);

        return back()->with('success', 'Transaksi kas berhasil dihapus.');
    }
}
