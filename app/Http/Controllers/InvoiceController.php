<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\InvoiceRequest;
use App\Models\CompanySetting;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\InvoiceService;
use App\Support\PrintPaper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service, private AuditLogService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('invoices.view');
        $canViewProfit = $request->user()->can('profit.view');
        $sort = in_array($request->sort, ['invoice_date', 'due_date', 'grand_total', 'remaining_amount'], true) ? $request->sort : 'invoice_date';
        $rows = Invoice::query()->with(['customer:id,name,company_name', 'creator:id,name'])
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) => $q->where('invoice_number', 'like', "%{$search}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('invoice_date', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('invoice_date', '<=', $date))
            ->orderBy($sort, $request->direction === 'asc' ? 'asc' : 'desc')->paginate(15)->withQueryString();

        if (! $canViewProfit) {
            $rows->getCollection()->each->makeHidden(['total_cost', 'gross_profit']);
        }

        return Inertia::render('Invoices/Index', [
            'rows' => $rows,
            'statuses' => array_column(InvoiceStatus::cases(), 'value'),
            'canViewProfit' => $canViewProfit,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('invoices.create');
        $settings = CompanySetting::first();

        return Inertia::render('Invoices/Create', [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'company_name', 'address']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'selling_price', 'purchase_price']),
            'defaultTax' => $settings?->default_tax_percentage ?? 11,
            'taxEnabled' => $settings?->tax_enabled ?? true,
            'discountEnabled' => $settings?->discount_enabled ?? true,
            'paymentSettings' => CompanySetting::query()->first(['bank_name', 'bank_account_number', 'bank_account_name']),
            'canViewCost' => $request->user()->can('profit.view'),
        ]);
    }

    public function store(InvoiceRequest $request)
    {
        $invoice = $this->service->create($request->validated(), $request->user()->id);

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->invoice_number} dibuat.");
    }

    public function edit(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice->load('items');
        $settings = CompanySetting::first();
        if ($request->user()->can('profit.view')) {
            $invoice->items->each->makeVisible(['purchase_price', 'cost_total', 'profit']);
        }

        return Inertia::render('Invoices/Create', [
            'invoice' => $invoice,
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'company_name', 'address']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'selling_price', 'purchase_price']),
            'defaultTax' => $settings?->default_tax_percentage ?? 11,
            'taxEnabled' => $settings?->tax_enabled ?? true,
            'discountEnabled' => $settings?->discount_enabled ?? true,
            'paymentSettings' => CompanySetting::query()->first(['bank_name', 'bank_account_number', 'bank_account_name']),
            'canViewCost' => $request->user()->can('profit.view'),
        ]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $invoice = $this->service->updateDraft($invoice, $request->validated(), $request->user()->id);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil diperbarui.');
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $invoice->load(['customer', 'items', 'payments.creator:id,name', 'creator:id,name', 'shippingDeposit', 'delivery']);
        if ($invoice->delivery?->proof_photo_path) {
            $invoice->delivery->proof_url = '/storage/'.ltrim($invoice->delivery->proof_photo_path, '/');
        }
        if ($invoice->delivery?->departure_photo_path) {
            $invoice->delivery->departure_proof_url = '/storage/'.ltrim($invoice->delivery->departure_photo_path, '/');
        }
        if (! $request->user()->can('profit.view')) {
            $invoice->makeHidden(['total_cost', 'gross_profit']);
        } else {
            $invoice->items->each->makeVisible(['purchase_price', 'cost_total', 'profit']);
        }

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'couriers' => Courier::withTrashed()
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $invoice->courier_id))
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'vehicle_type', 'license_plate', 'is_active', 'deleted_at']),
            'canViewCost' => $request->user()->can('profit.view'),
            'canEditInvoice' => $request->user()->can('invoices.create') && $invoice->status !== InvoiceStatus::Cancelled,
            'canDeleteInvoice' => $request->user()->hasAnyRole(['Super Admin', 'Admin'])
                && $request->user()->can('invoices.delete'),
        ]);
    }

    public function issue(Request $request, Invoice $invoice)
    {
        $this->authorize('issue', $invoice);
        $data = $request->validate([
            'courier_id' => ['required', 'exists:couriers,id'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'shipping_paid_now' => ['required', 'boolean'],
        ]);
        $this->service->issue(
            $invoice,
            $request->user()->id,
            (bool) $data['shipping_paid_now'],
            (int) $data['courier_id'],
            $data['shipping_cost'],
        );

        return back()->with('success', 'Invoice berhasil diterbitkan.');
    }

    public function updateShipping(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate([
            'courier_id' => ['required', 'exists:couriers,id'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'shipping_paid_now' => ['required', 'boolean'],
        ]);

        $this->service->updateShipping(
            $invoice,
            $request->user()->id,
            (int) $data['courier_id'],
            $data['shipping_cost'],
            (bool) $data['shipping_paid_now'],
        );

        return back()->with('success', 'Kurir dan ongkir invoice berhasil diperbarui.');
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorize('cancel', $invoice);
        $this->service->cancel($invoice, $request->user()->id);

        return back()->with('success', 'Invoice dibatalkan.');
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);
        $this->service->deleteInvoice($invoice);

        return redirect()->route('invoices.index')->with('success', 'Invoice beserta riwayat pembayaran dan Cash Masuk terkait berhasil dihapus.');
    }

    public function print(Request $request, Invoice $invoice)
    {
        $this->authorize('print', $invoice);
        $this->audit->record('print', 'invoice', $invoice);

        return view('invoices.print', [...$this->printData($invoice), 'autoPrint' => true]);
    }

    public function pdf(Request $request, Invoice $invoice)
    {
        $this->authorize('print', $invoice);
        $this->audit->record('download_pdf', 'invoice', $invoice);

        $data = $this->printData($invoice);

        return Pdf::loadView('invoices.print', $data)
            ->setPaper(PrintPaper::dompdfPaper($data['company']), PrintPaper::dompdfOrientation($data['company']))
            ->download(str_replace('/', '-', $invoice->invoice_number).'.pdf');
    }

    private function printData(Invoice $invoice): array
    {
        return ['invoice' => $invoice->load(['customer', 'items', 'creator:id,name']), 'company' => CompanySetting::first()];
    }
}
