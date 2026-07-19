<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Exports\SalesReportExport;
use App\Models\CashTransaction;
use App\Models\CombinedInvoiceDocument;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('reports.view');

        return Inertia::render('Reports/Home', [
            'canViewProfit' => $request->user()->can('profit.view'),
        ]);
    }

    public function invoices(Request $request)
    {
        $this->authorize('reports.view');
        $query = $this->invoiceQuery($request);
        $summary = (clone $query)->selectRaw(
            'COUNT(*) as invoice_count, COALESCE(SUM(grand_total),0) as grand_total, COALESCE(SUM(paid_amount),0) as paid_total, COALESCE(SUM(remaining_amount),0) as remaining_total'
        )->first();
        $rows = $query->with('customer:id,name,company_name')->latest('invoice_date')->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('Reports/Invoices', [
            'summary' => $summary,
            'rows' => $rows,
            'filters' => $this->filters($request, ['status']),
        ]);
    }

    public function combinedInvoices(Request $request)
    {
        $this->authorize('reports.view');
        $query = CombinedInvoiceDocument::query()
            ->when($request->date_from, fn (Builder $query, string $date) => $query->whereDate('opened_at', '>=', $date))
            ->when($request->date_to, fn (Builder $query, string $date) => $query->whereDate('opened_at', '<=', $date))
            ->when($request->search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('facture_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%"))
                ->orWhereHas('invoices', fn (Builder $query) => $query->where('invoice_number', 'like', "%{$search}%"))));

        $summaryDocuments = (clone $query)->with('invoices:id,grand_total,remaining_amount')->get();
        $summary = [
            'facture_count' => $summaryDocuments->count(),
            'customer_count' => $summaryDocuments->pluck('customer_id')->unique()->count(),
            'invoice_count' => $summaryDocuments->sum(fn ($document) => $document->invoices->count()),
            'grand_total' => (string) $summaryDocuments->sum(fn ($document) => $document->invoices->sum('grand_total')),
            'remaining_total' => (string) $summaryDocuments->sum(fn ($document) => $document->invoices->sum('remaining_amount')),
        ];
        $rows = $query
            ->with('customer:id,customer_code,name,company_name,phone')
            ->withCount('invoices')
            ->withSum('invoices as grand_total', 'grand_total')
            ->withSum('invoices as paid_total', 'paid_amount')
            ->withSum('invoices as remaining_total', 'remaining_amount')
            ->latest('opened_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Reports/CombinedInvoices', [
            'summary' => $summary,
            'rows' => $rows,
            'filters' => $this->filters($request),
        ]);
    }

    public function cash(Request $request)
    {
        $this->authorize('reports.view');
        $query = CashTransaction::query()
            ->when($request->date_from, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($request->date_to, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date))
            ->when($request->type, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($request->search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('transaction_number', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('reference_number', 'like', "%{$search}%")));
        $summary = (clone $query)->selectRaw(
            "COUNT(*) as transaction_count, COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END),0) as incoming_total, COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END),0) as outgoing_total"
        )->first();
        $summary->balance = (string) ((float) $summary->incoming_total - (float) $summary->outgoing_total);
        $rows = $query->latest('transaction_date')->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('Reports/Cash', [
            'summary' => $summary,
            'rows' => $rows,
            'filters' => $this->filters($request, ['type']),
        ]);
    }

    public function margins(Request $request)
    {
        $this->authorize('reports.view');
        abort_unless($request->user()->can('profit.view'), 403);
        $query = $this->factureMarginQuery($request);
        $documentIds = (clone $query)->pluck('id');

        $invoiceTotals = Invoice::query()
            ->join('combined_invoice_document_invoice as link', 'link.invoice_id', '=', 'invoices.id')
            ->whereIn('link.combined_invoice_document_id', $documentIds)
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(invoices.subtotal - invoices.discount_amount), 0) as sales_total, COALESCE(SUM(invoices.total_cost), 0) as cost_total, COALESCE(SUM(invoices.gross_profit), 0) as gross_margin_total, COALESCE(SUM(invoices.shipping_cost), 0) as shipping_total')
            ->first();
        $commissionTotal = (float) CombinedInvoiceDocument::query()
            ->whereIn('id', $documentIds)
            ->withSum('commissions as commission_total', 'commission_amount')
            ->get()
            ->sum('commission_total');
        $grossMarginTotal = (float) $invoiceTotals->gross_margin_total;
        $factureShippingTotal = (float) (clone $query)->sum('shipping_cost');
        $shippingTotal = (float) $invoiceTotals->shipping_total + $factureShippingTotal;
        $summary = [
            'facture_count' => $documentIds->count(),
            'invoice_count' => (int) $invoiceTotals->invoice_count,
            'sales_total' => (string) $invoiceTotals->sales_total,
            'cost_total' => (string) $invoiceTotals->cost_total,
            'gross_margin_total' => (string) $grossMarginTotal,
            'commission_total' => (string) $commissionTotal,
            'shipping_total' => (string) $shippingTotal,
            'net_margin_total' => (string) ($grossMarginTotal - $commissionTotal - $shippingTotal),
        ];
        $rows = $query
            ->with('customer:id,name,company_name')
            ->withCount('invoices')
            ->withSum('invoices as subtotal_total', 'subtotal')
            ->withSum('invoices as discount_total', 'discount_amount')
            ->withSum('invoices as cost_total', 'total_cost')
            ->withSum('invoices as gross_margin_total', 'gross_profit')
            ->withSum('invoices as shipping_total', 'shipping_cost')
            ->withSum('commissions as commission_total', 'commission_amount')
            ->latest('opened_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();
        $rows->getCollection()->each(function (CombinedInvoiceDocument $document) {
            $shippingTotal = (float) $document->shipping_total + (float) $document->shipping_cost;
            $document->shipping_total = fmod($shippingTotal, 1.0) === 0.0 ? (int) $shippingTotal : $shippingTotal;
        });

        return Inertia::render('Reports/Margins', [
            'summary' => $summary,
            'rows' => $rows,
            'filters' => $this->filters($request),
        ]);
    }

    public function export(Request $request, string $format)
    {
        $this->authorize('reports.export');
        $name = 'laporan-invoice-'.now()->format('Ymd-His');
        if ($format === 'xlsx') {
            return Excel::download(new SalesReportExport($request->date_from, $request->date_to), $name.'.xlsx');
        }
        $rows = $this->invoiceQuery($request)->with('customer')->latest('invoice_date')->get();
        if ($format === 'pdf') {
            return Pdf::loadView('reports.sales', ['rows' => $rows])->setPaper('a4', 'landscape')->download($name.'.pdf');
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nomor', 'Tanggal', 'Pelanggan', 'Grand Total', 'Terbayar', 'Sisa', 'Status']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->invoice_number, $row->invoice_date->format('Y-m-d'), $row->billing_name, $row->grand_total, $row->paid_amount, $row->remaining_amount, $row->status->value]);
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function invoiceQuery(Request $request): Builder
    {
        return Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->when($request->date_from, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '>=', $date))
            ->when($request->date_to, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '<=', $date))
            ->when($request->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('purchase_order_number', 'like', "%{$search}%")
                ->orWhere('billing_name', 'like', "%{$search}%")
                ->orWhere('billing_company', 'like', "%{$search}%")));
    }

    private function factureMarginQuery(Request $request): Builder
    {
        return CombinedInvoiceDocument::query()
            ->when($request->date_from, fn (Builder $query, string $date) => $query->whereDate('opened_at', '>=', $date))
            ->when($request->date_to, fn (Builder $query, string $date) => $query->whereDate('opened_at', '<=', $date))
            ->when($request->search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('facture_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%"))
                ->orWhereHas('invoices', fn (Builder $query) => $query
                    ->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('purchase_order_number', 'like', "%{$search}%")
                    ->orWhere('billing_name', 'like', "%{$search}%")
                    ->orWhere('billing_company', 'like', "%{$search}%"))));
    }

    private function filterOutstandingInvoices(Builder $query, Request $request): Builder
    {
        return $query
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])
            ->when($request->date_from, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '>=', $date))
            ->when($request->date_to, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '<=', $date))
            ->when($request->search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('billing_name', 'like', "%{$search}%")
                ->orWhere('billing_company', 'like', "%{$search}%")));
    }

    private function filters(Request $request, array $extra = []): array
    {
        return collect(['search', 'date_from', 'date_to', ...$extra])
            ->mapWithKeys(fn (string $key) => [$key => (string) $request->input($key, '')])
            ->all();
    }
}
