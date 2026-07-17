<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\FactureCommission;
use App\Services\CashTransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FactureCommissionController extends Controller
{
    public function __construct(private CashTransactionService $cash) {}

    public function index(Request $request)
    {
        $this->authorize('profit.view');
        $status = $request->string('status')->toString();

        $commissions = FactureCommission::query()
            ->with('document.customer:id,name,company_name')
            ->when($request->search, fn (Builder $query, string $search) => $query
                ->whereHas('document', fn (Builder $query) => $query
                    ->where('facture_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%"))))
            ->when(in_array($status, ['unpaid', 'paid'], true), fn (Builder $query) => $query->where('status', $status))
            ->latest('facture_payment_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('FactureCommissions/Index', [
            'commissions' => $commissions,
            'filters' => ['search' => $request->string('search')->toString(), 'status' => $status],
            'summary' => [
                'unpaid' => (string) FactureCommission::where('status', 'unpaid')->sum('commission_amount'),
                'paid' => (string) FactureCommission::where('status', 'paid')->sum('commission_amount'),
            ],
        ]);
    }

    public function show(FactureCommission $factureCommission)
    {
        $this->authorize('profit.view');
        $factureCommission->load('document.customer:id,name,company_name');
        $invoices = $factureCommission->document->invoices()
            ->with('items')
            ->orderBy('invoice_date')
            ->get();

        return Inertia::render('FactureCommissions/Show', [
            'commission' => $factureCommission,
            'document' => $factureCommission->document,
            'customer' => $factureCommission->document->customer,
            'invoices' => $invoices->map(fn ($invoice) => [
                ...$invoice->only(['id', 'invoice_number', 'invoice_date', 'grand_total', 'total_cost', 'gross_profit']),
                'items' => $invoice->items->map(function ($item) {
                    $item->makeVisible(['purchase_price', 'cost_total', 'profit']);

                    return $item->only(['id', 'product_name_snapshot', 'unit_snapshot', 'quantity', 'purchase_price', 'selling_price', 'cost_total', 'line_subtotal', 'profit']);
                }),
            ]),
            'canPay' => request()->user()->can('payments.manage') && $factureCommission->status === 'unpaid',
            'canManage' => request()->user()->can('payments.manage'),
            'commissionWarningPercentage' => (float) (CompanySetting::first()?->commission_margin_warning_percentage ?? 10),
            'today' => today()->toDateString(),
        ]);
    }

    public function pay(Request $request, FactureCommission $factureCommission)
    {
        $this->authorize('payments.manage');
        $this->authorize('profit.view');
        $data = $request->validate([
            'paid_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:transfer,cash,card,qris,virtual_account,other'],
            'payment_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $factureCommission, $data) {
            $commission = FactureCommission::query()->lockForUpdate()->findOrFail($factureCommission->id);
            abort_if($commission->status === 'paid', 422, 'Komisi Faktur ini sudah dibayar.');
            $commission->load('document');

            $transaction = $this->cash->create('out', [
                'transaction_date' => $data['paid_date'],
                'category' => 'Komisi Faktur',
                'description' => 'Pembayaran Komisi Faktur '.$commission->document->facture_number,
                'payment_method' => $data['payment_method'],
                'amount' => $commission->commission_amount,
                'reference_number' => $commission->document->facture_number,
                'notes' => $data['payment_notes'] ?: 'Komisi Faktur '.$commission->document->facture_number,
            ], $request->user()->id);

            $commission->update([
                'status' => 'paid',
                'paid_date' => $data['paid_date'],
                'payment_method' => $data['payment_method'],
                'payment_notes' => $data['payment_notes'] ?? null,
                'cash_transaction_id' => $transaction->id,
                'paid_by' => $request->user()->id,
                'paid_at' => now(),
            ]);
        });

        return back()->with('success', 'Komisi Faktur berhasil dibayar dan dicatat ke Kas Keluar.');
    }

    public function update(Request $request, FactureCommission $factureCommission)
    {
        $this->authorize('payments.manage');
        $this->authorize('profit.view');
        $data = $request->validate([
            'facture_payment_date' => ['required', 'date'],
            'commission_base' => ['required', 'in:facture_total,margin'],
            'commission_type' => ['required', 'in:nominal,percentage'],
            'commission_value' => ['required', 'numeric', 'gt:0', Rule::when($request->input('commission_type') === 'percentage', ['integer', 'max:100'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_date' => [$factureCommission->status === 'paid' ? 'required' : 'nullable', 'date'],
            'payment_method' => [$factureCommission->status === 'paid' ? 'required' : 'nullable', 'in:transfer,cash,card,qris,virtual_account,other'],
            'payment_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $factureCommission, $data) {
            $commission = FactureCommission::query()->lockForUpdate()->findOrFail($factureCommission->id);
            $baseAmount = $data['commission_base'] === 'margin' ? (float) $commission->margin_total : (float) $commission->facture_total;
            $amount = $data['commission_type'] === 'percentage'
                ? round($baseAmount * (float) $data['commission_value'] / 100, 2)
                : round((float) $data['commission_value'], 2);

            $commission->update([
                ...$data,
                'base_amount' => $baseAmount,
                'commission_amount' => $amount,
            ]);

            if ($commission->status === 'paid') {
                $this->cash->syncFactureCommission($commission->fresh(), $request->user()->id);
            }
        });

        return back()->with('success', 'Komisi Faktur berhasil diperbarui. Cash Keluar telah disinkronkan.');
    }

    public function destroy(FactureCommission $factureCommission)
    {
        $this->authorize('payments.manage');
        $this->authorize('profit.view');

        DB::transaction(function () use ($factureCommission) {
            $commission = FactureCommission::query()->lockForUpdate()->findOrFail($factureCommission->id);
            $transaction = $commission->cashTransaction;
            $commission->delete();
            if ($transaction) {
                $this->cash->deleteFactureCommissionCash($transaction);
            }
        });

        return redirect()->route('facture-commissions.index')
            ->with('success', 'Komisi Faktur berhasil dihapus. Cash Keluar terkait juga dihapus.');
    }
}
