<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\PaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $service) {}

    public function index(Request $request)
    {
        $this->authorize('payments.view');
        $rows = Payment::query()->with(['invoice:id,invoice_number,billing_name,grand_total', 'creator:id,name'])
            ->when($request->search, fn ($query, $search) => $query->where('payment_number', 'like', "%{$search}%")->orWhereHas('invoice', fn ($q) => $q->where('invoice_number', 'like', "%{$search}%")))
            ->latest('payment_date')->paginate(15)->withQueryString();

        return Inertia::render('Payments/Index', ['rows' => $rows]);
    }

    public function receivables(Request $request)
    {
        $this->authorize('payments.view');
        $rows = Invoice::with('customer:id,name,company_name')->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])->latest('due_date')->paginate(15);

        return Inertia::render('Payments/Receivables', ['rows' => $rows]);
    }

    public function store(PaymentRequest $request, Invoice $invoice)
    {
        $data = $request->validated();
        if ($request->hasFile('payment_proof')) {
            $data['payment_proof'] = $request->file('payment_proof')->store('payment-proofs', 'public');
        }
        $this->service->record($invoice, $data, $request->user()->id);

        return back()->with('success', 'Pembayaran tercatat dan status invoice diperbarui.');
    }
}
