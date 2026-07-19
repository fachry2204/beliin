<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\CombinedInvoiceDocument;
use App\Models\CompanySetting;
use App\Models\Courier;
use App\Models\CourierDelivery;
use App\Models\Customer;
use App\Models\FactureCommission;
use App\Models\Payment;
use App\Services\CombinedInvoiceService;
use App\Services\PaymentService;
use App\Support\PrintPaper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CombinedInvoiceController extends Controller
{
    public function __construct(
        private CombinedInvoiceService $documents,
        private PaymentService $payments,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('invoices.view');
        $canViewProfit = $request->user()->can('profit.view');

        $documents = CombinedInvoiceDocument::query()
            ->with('customer:id,customer_code,name,company_name,phone')
            ->withCount('invoices')
            ->withSum('invoices as grand_total', 'grand_total')
            ->withSum('invoices as paid_total', 'paid_amount')
            ->withSum('invoices as remaining_total', 'remaining_amount')
            ->when($canViewProfit, fn (Builder $query) => $query
                ->withSum('invoices as gross_profit_total', 'gross_profit')
                ->withSum('invoices as subtotal_total', 'subtotal')
                ->withSum('invoices as discount_total', 'discount_amount'))
            ->when($request->search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('facture_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%"))))
            ->latest('opened_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('CombinedInvoices/Index', [
            'documents' => $documents,
            'canViewProfit' => $canViewProfit,
            'canCreate' => $request->user()->can('invoices.create'),
        ]);
    }

    public function create()
    {
        $this->authorize('invoices.create');

        $customers = Customer::query()
            ->select(['id', 'customer_code', 'name', 'company_name', 'phone'])
            ->whereHas('invoices', fn (Builder $query) => $this->eligible($query))
            ->with(['invoices' => fn ($query) => $this->eligible($query)
                ->select(['id', 'customer_id', 'invoice_number', 'invoice_date', 'grand_total', 'paid_amount', 'remaining_amount'])
                ->orderBy('invoice_date')])
            ->orderByRaw('COALESCE(company_name, name)')
            ->get();

        return Inertia::render('CombinedInvoices/Create', [
            'customers' => $customers,
            'couriers' => Courier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'vehicle_type', 'license_plate']),
            'today' => today()->toDateString(),
            'defaultDueDate' => today()->addWeek()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('invoices.create');
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'integer', 'distinct', 'exists:invoices,id'],
            'use_due_date' => ['required', 'boolean'],
            'due_date' => ['nullable', 'required_if:use_due_date,true', 'date', 'after_or_equal:today'],
            'courier_id' => [Rule::requiredIf((float) $request->input('shipping_cost', 0) > 0), 'nullable', 'integer', 'exists:couriers,id'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $eligibleIds = $this->eligible($customer->invoices())
            ->whereIn('id', $data['invoice_ids'])
            ->pluck('id');
        if ($eligibleIds->count() !== count($data['invoice_ids'])) {
            throw ValidationException::withMessages([
                'invoice_ids' => 'Pilihan invoice tidak valid, sudah lunas, atau sudah masuk ke faktur lain.',
            ]);
        }

        $document = $this->documents->create(
            $customer,
            $eligibleIds->all(),
            $data['use_due_date'] ? $data['due_date'] : null,
            filled($data['courier_id'] ?? null) ? (int) $data['courier_id'] : null,
            $data['shipping_cost'] ?? 0,
            $request->user()->id,
        );

        return redirect()->route('combined-invoices.show', $document)
            ->with('success', 'Faktur baru berhasil dibuat.');
    }

    public function show(Request $request, CombinedInvoiceDocument $combinedInvoice)
    {
        $this->authorize('invoices.view');
        $canViewProfit = $request->user()->can('profit.view');

        $hasFacturePayments = $combinedInvoice->payments()->exists();
        $deletionLockReason = $this->deletionLockReason($combinedInvoice);

        return Inertia::render('CombinedInvoices/Show', [
            ...$this->combinedData($combinedInvoice, $canViewProfit),
            'canViewProfit' => $canViewProfit,
            'canManagePayments' => $request->user()->can('payments.manage'),
            'canEditDueDate' => $request->user()->can('invoices.create') && $combinedInvoice->status === 'open',
            'canEdit' => $request->user()->can('invoices.create') && ! $hasFacturePayments && $combinedInvoice->status === 'open',
            'canDelete' => $request->user()->can('invoices.delete'),
            'deletionLocked' => $deletionLockReason !== null,
            'deletionLockReason' => $deletionLockReason,
            'today' => today()->toDateString(),
            'defaultDueDate' => today()->addWeek()->toDateString(),
            'commissionWarningPercentage' => (float) (CompanySetting::first()?->commission_margin_warning_percentage ?? 10),
        ]);
    }

    public function edit(CombinedInvoiceDocument $combinedInvoice)
    {
        $this->authorize('invoices.create');
        $this->ensureEditable($combinedInvoice);

        $customer = $combinedInvoice->customer;
        $invoices = $customer->invoices()
            ->where(function (Builder $query) use ($combinedInvoice) {
                $query->whereHas('combinedDocuments', fn (Builder $query) => $query->whereKey($combinedInvoice->id))
                    ->orWhere(fn (Builder $query) => $this->eligible($query));
            })
            ->select(['id', 'customer_id', 'invoice_number', 'invoice_date', 'grand_total', 'paid_amount', 'remaining_amount'])
            ->orderBy('invoice_date')
            ->get();

        return Inertia::render('CombinedInvoices/Create', [
            'customers' => [[
                ...$customer->only(['id', 'customer_code', 'name', 'company_name', 'phone']),
                'invoices' => $invoices,
            ]],
            'today' => today()->toDateString(),
            'defaultDueDate' => today()->addWeek()->toDateString(),
            'couriers' => Courier::withTrashed()
                ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $combinedInvoice->courier_id))
                ->orderBy('name')->get(['id', 'name', 'vehicle_type', 'license_plate', 'deleted_at']),
            'document' => [
                'id' => $combinedInvoice->id,
                'facture_number' => $combinedInvoice->facture_number,
                'customer_id' => $combinedInvoice->customer_id,
                'invoice_ids' => $combinedInvoice->invoices()->pluck('invoices.id'),
                'due_date' => $combinedInvoice->due_date?->toDateString(),
                'courier_id' => $combinedInvoice->courier_id,
                'shipping_cost' => $combinedInvoice->shipping_cost,
            ],
        ]);
    }

    public function update(Request $request, CombinedInvoiceDocument $combinedInvoice)
    {
        $this->authorize('invoices.create');
        $this->ensureEditable($combinedInvoice);
        $data = $this->validateDocument($request);
        if ((int) $data['customer_id'] !== $combinedInvoice->customer_id) {
            throw ValidationException::withMessages(['customer_id' => 'Pelanggan Faktur tidak dapat diubah.']);
        }

        $allowedIds = $combinedInvoice->customer->invoices()
            ->whereIn('id', $data['invoice_ids'])
            ->where(function (Builder $query) use ($combinedInvoice) {
                $query->whereHas('combinedDocuments', fn (Builder $query) => $query->whereKey($combinedInvoice->id))
                    ->orWhere(fn (Builder $query) => $this->eligible($query));
            })->pluck('id');
        if ($allowedIds->count() !== count($data['invoice_ids'])) {
            throw ValidationException::withMessages(['invoice_ids' => 'Pilihan invoice tidak valid atau sudah masuk Faktur lain.']);
        }

        DB::transaction(function () use ($combinedInvoice, $data, $allowedIds, $request) {
            $combinedInvoice->update(['due_date' => $data['use_due_date'] ? $data['due_date'] : null]);
            $combinedInvoice->invoices()->sync($allowedIds);
            $this->documents->updateShipping(
                $combinedInvoice,
                filled($data['courier_id'] ?? null) ? (int) $data['courier_id'] : null,
                $data['shipping_cost'] ?? 0,
                $request->user()->id,
            );
        });

        return redirect()->route('combined-invoices.show', $combinedInvoice)->with('success', 'Faktur berhasil diperbarui.');
    }

    public function destroy(CombinedInvoiceDocument $combinedInvoice)
    {
        $this->authorize('invoices.delete');
        abort_if($reason = $this->deletionLockReason($combinedInvoice), 422, $reason);
        $combinedInvoice->delete();

        return redirect()->route('combined-invoices.index')->with('success', 'Faktur berhasil dihapus. Invoice tetap tersimpan.');
    }

    public function updateDueDate(Request $request, CombinedInvoiceDocument $combinedInvoice)
    {
        $this->authorize('invoices.create');
        abort_if($combinedInvoice->status !== 'open', 422, 'Tanggal jatuh tempo Faktur yang sudah lunas tidak dapat diubah.');
        $data = $request->validate([
            'use_due_date' => ['required', 'boolean'],
            'due_date' => ['nullable', 'required_if:use_due_date,true', 'date'],
        ]);
        $combinedInvoice->update(['due_date' => $data['use_due_date'] ? $data['due_date'] : null]);

        return back()->with('success', 'Jatuh tempo Faktur berhasil diperbarui.');
    }

    public function print(CombinedInvoiceDocument $combinedInvoice)
    {
        $this->authorize('invoices.view');

        return view('combined-invoices.print', [
            ...$this->combinedData($combinedInvoice, false),
            'company' => CompanySetting::first(),
            'autoPrint' => true,
        ]);
    }

    public function pdf(CombinedInvoiceDocument $combinedInvoice)
    {
        $this->authorize('invoices.view');
        $data = [...$this->combinedData($combinedInvoice, false), 'company' => CompanySetting::first()];

        return Pdf::loadView('combined-invoices.print', $data)
            ->setPaper(PrintPaper::dompdfPaper($data['company']), PrintPaper::dompdfOrientation($data['company']))
            ->download(str_replace('/', '-', $data['document']->facture_number).'.pdf');
    }

    public function pay(Request $request, CombinedInvoiceDocument $combinedInvoice)
    {
        $this->authorize('payments.manage');
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:transfer,cash,card,qris,virtual_account,other'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_proof' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,pdf'],
            'commission_enabled' => ['required', 'boolean'],
            'commission_base' => [Rule::requiredIf($request->boolean('commission_enabled')), 'nullable', 'in:facture_total,margin'],
            'commission_type' => [Rule::requiredIf($request->boolean('commission_enabled')), 'nullable', 'in:nominal,percentage'],
            'commission_value' => [Rule::requiredIf($request->boolean('commission_enabled')), 'nullable', 'numeric', 'gt:0', Rule::when($request->input('commission_type') === 'percentage', ['integer', 'max:100'])],
            'commission_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($request->hasFile('payment_proof')) {
            $data['payment_proof'] = $request->file('payment_proof')->store('payment-proofs', 'public');
        }

        $invoices = $this->outstanding($combinedInvoice->invoices())->orderBy('due_date')->orderBy('invoice_date')->get();
        $totalRemaining = (string) $invoices->sum('remaining_amount');
        if (bccomp((string) $data['amount'], $totalRemaining, 2) > 0) {
            throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi total sisa Faktur.']);
        }

        $factureTotal = (float) $combinedInvoice->invoices()->sum('grand_total');
        $marginTotal = (float) $combinedInvoice->invoices()->sum('gross_profit');
        $commissionAmount = 0.0;
        $commissionBaseAmount = 0.0;
        if ($data['commission_enabled']) {
            $commissionBaseAmount = $data['commission_base'] === 'margin' ? $marginTotal : $factureTotal;
            if ($data['commission_type'] === 'percentage' && $commissionBaseAmount <= 0) {
                throw ValidationException::withMessages(['commission_base' => 'Margin Faktur harus lebih dari nol untuk komisi persentase.']);
            }
            $commissionAmount = $data['commission_type'] === 'percentage'
                ? round($commissionBaseAmount * (float) $data['commission_value'] / 100, 2)
                : round((float) $data['commission_value'], 2);
        }

        DB::transaction(function () use ($invoices, $data, $request, $combinedInvoice, $factureTotal, $marginTotal, $commissionBaseAmount, $commissionAmount) {
            $unallocated = (string) $data['amount'];
            foreach ($invoices as $invoice) {
                if (bccomp($unallocated, '0', 2) <= 0) {
                    break;
                }
                $allocation = bccomp($unallocated, (string) $invoice->remaining_amount, 2) >= 0
                    ? (string) $invoice->remaining_amount
                    : $unallocated;
                $payment = $this->payments->record($invoice, array_merge($data, [
                    'amount' => $allocation,
                    'notes' => trim('Pembayaran Faktur '.$combinedInvoice->facture_number.'. '.($data['notes'] ?? '')),
                ]), $request->user()->id);
                $this->payments->attachToCombinedInvoice($payment, $combinedInvoice, $request->user()->id);
                $unallocated = bcsub($unallocated, $allocation, 2);
            }

            if ($commissionAmount > 0) {
                FactureCommission::create([
                    'combined_invoice_document_id' => $combinedInvoice->id,
                    'facture_payment_date' => $data['payment_date'],
                    'commission_base' => $data['commission_base'],
                    'commission_type' => $data['commission_type'],
                    'commission_value' => $data['commission_value'],
                    'base_amount' => $commissionBaseAmount,
                    'facture_total' => $factureTotal,
                    'margin_total' => $marginTotal,
                    'commission_amount' => $commissionAmount,
                    'status' => 'unpaid',
                    'notes' => $data['commission_notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        $this->documents->closeIfSettled($combinedInvoice->customer);

        return redirect()->route('combined-invoices.show', $combinedInvoice)
            ->with('success', 'Pembayaran Faktur berhasil dicatat. Komisi tersimpan di halaman Komisi Faktur dan belum masuk Kas Keluar.');
    }

    public function updatePayment(Request $request, CombinedInvoiceDocument $combinedInvoice, Payment $payment)
    {
        $this->authorize('payments.manage');
        abort_unless($payment->combined_invoice_document_id === $combinedInvoice->id, 404);
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:transfer,cash,card,qris,virtual_account,other'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->payments->update($payment, $data, $request->user()->id);

        return redirect()->route('combined-invoices.show', $combinedInvoice)
            ->with('success', 'Pembayaran Faktur berhasil dikoreksi dan Cash Masuk telah disinkronkan.');
    }

    private function combinedData(CombinedInvoiceDocument $document, bool $canViewProfit): array
    {
        $columns = ['invoices.id', 'invoice_number', 'purchase_order_number', 'invoice_date', 'due_date', 'grand_total', 'paid_amount', 'remaining_amount', 'status'];
        if ($canViewProfit) {
            $columns = [...$columns, 'subtotal', 'discount_amount', 'gross_profit'];
        }
        $invoices = $document->invoices()->orderBy('invoice_date')->get($columns);
        abort_if($invoices->isEmpty(), 404, 'Faktur tidak memiliki invoice.');

        $totals = [
            'grand_total' => (string) $invoices->sum('grand_total'),
            'paid_total' => (string) $invoices->sum('paid_amount'),
            'remaining_total' => (string) $invoices->sum('remaining_amount'),
        ];
        if ($canViewProfit) {
            $totals['gross_profit_total'] = (string) $invoices->sum('gross_profit');
            $totals['profit_base_total'] = (string) $invoices->sum(
                fn ($invoice) => (float) $invoice->subtotal - (float) $invoice->discount_amount
            );
            $totals['commission_total'] = (string) $document->commissions()->sum('commission_amount');
        }

        return [
            'document' => $document,
            'customer' => $document->customer->only(['id', 'customer_code', 'name', 'company_name', 'phone', 'email', 'address']),
            'invoices' => $invoices,
            'payments' => $document->payments()
                ->with('invoice:id,invoice_number')
                ->latest('payment_date')
                ->latest('id')
                ->get(['id', 'invoice_id', 'payment_number', 'payment_date', 'amount', 'payment_method', 'bank_name', 'reference_number', 'notes']),
            'totals' => $totals,
        ];
    }

    private function eligible($query)
    {
        return $this->outstanding($query)->whereDoesntHave('combinedDocuments');
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'integer', 'distinct', 'exists:invoices,id'],
            'use_due_date' => ['required', 'boolean'],
            'due_date' => ['nullable', 'required_if:use_due_date,true', 'date'],
            'courier_id' => [Rule::requiredIf((float) $request->input('shipping_cost', 0) > 0), 'nullable', 'integer', 'exists:couriers,id'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function ensureEditable(CombinedInvoiceDocument $document): void
    {
        abort_if($document->status !== 'open' || $document->payments()->exists(), 422, 'Faktur yang sudah menerima pembayaran tidak dapat diedit atau dihapus.');
    }

    private function deletionLockReason(CombinedInvoiceDocument $document): ?string
    {
        $reasons = [];

        if ($document->payments()->exists() || $document->status !== 'open') {
            $reasons[] = 'Faktur sudah memiliki pembayaran';
        }

        $deliveryStarted = $document->invoices()
            ->whereHas('delivery', fn (Builder $query) => $query->whereIn('status', [
                CourierDelivery::ACCEPTED,
                CourierDelivery::IN_TRANSIT,
                CourierDelivery::DELIVERED,
            ]))->exists();

        if ($deliveryStarted) {
            $reasons[] = 'kurir sudah mengambil atau menjalankan tugas pengiriman pada invoice di dalam Faktur';
        }

        return $reasons === []
            ? null
            : 'Faktur tidak dapat dihapus karena '.implode(' dan ', $reasons).'.';
    }

    private function outstanding($query)
    {
        return $query->whereIn('status', [
            InvoiceStatus::Unpaid,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Overdue,
        ])->where('remaining_amount', '>', 0);
    }
}
