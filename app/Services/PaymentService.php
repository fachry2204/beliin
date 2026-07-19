<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\CombinedInvoiceDocument;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private AuditLogService $audit, private CashTransactionService $cash, private CombinedInvoiceService $combinedInvoices) {}

    public function record(Invoice $invoice, array $data, int $userId): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === InvoiceStatus::Cancelled) {
                throw ValidationException::withMessages(['invoice' => 'Invoice dibatalkan.']);
            }
            if (bccomp((string) $data['amount'], (string) $invoice->remaining_amount, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi sisa tagihan.']);
            }
            $payment = $invoice->payments()->create(array_merge($data, [
                'payment_number' => 'PAY/'.now()->format('Ymd').'/'.strtoupper(substr((string) str()->uuid(), 0, 8)),
                'created_by' => $userId,
            ]));
            $paid = (string) $invoice->payments()->sum('amount');
            $remaining = bcsub((string) $invoice->grand_total, $paid, 2);
            $status = bccomp($paid, '0', 2) === 0 ? InvoiceStatus::Unpaid : (bccomp($paid, (string) $invoice->grand_total, 2) >= 0 ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid);
            $invoice->update(['paid_amount' => $paid, 'remaining_amount' => $remaining, 'status' => $status]);
            $this->audit->record('create', 'payment', $payment, null, $payment->toArray());
            $this->cash->createFromPayment($payment);
            Cache::forget('dashboard.metrics');
            $this->combinedInvoices->closeIfSettled($invoice->customer);

            return $payment;
        });
    }

    public function update(Payment $payment, array $data, int $userId): Payment
    {
        return DB::transaction(function () use ($payment, $data, $userId) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $otherPaid = (string) $invoice->payments()->where('id', '!=', $payment->id)->sum('amount');
            $maximum = bcsub((string) $invoice->grand_total, $otherPaid, 2);

            if (bccomp((string) $data['amount'], $maximum, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal koreksi melebihi sisa alokasi yang dapat dibayar untuk invoice ini.',
                ]);
            }

            $old = $payment->toArray();
            $payment->update($data);
            $paid = (string) $invoice->payments()->sum('amount');
            $remaining = bcsub((string) $invoice->grand_total, $paid, 2);
            $status = bccomp($paid, '0', 2) === 0
                ? InvoiceStatus::Unpaid
                : (bccomp($paid, (string) $invoice->grand_total, 2) >= 0 ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid);
            $invoice->update(['paid_amount' => $paid, 'remaining_amount' => $remaining, 'status' => $status]);
            $this->audit->record('update', 'payment', $payment, $old, $payment->fresh()->toArray());
            $this->cash->syncFromPayment($payment, $userId);
            if ($payment->combinedInvoice) {
                $this->combinedInvoices->refreshStatus($payment->combinedInvoice);
            }
            Cache::forget('dashboard.metrics');

            return $payment->fresh();
        });
    }

    public function attachToCombinedInvoice(Payment $payment, CombinedInvoiceDocument $document, int $userId): Payment
    {
        return DB::transaction(function () use ($payment, $document, $userId) {
            $payment->update(['combined_invoice_document_id' => $document->id]);
            $payment = $payment->fresh(['invoice', 'combinedInvoice']);
            $this->cash->syncFromPayment($payment, $userId);

            return $payment;
        });
    }
}
