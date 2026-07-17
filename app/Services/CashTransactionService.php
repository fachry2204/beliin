<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\CashTransactionSequence;
use App\Models\CourierShippingDeposit;
use App\Models\FactureCommission;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashTransactionService
{
    public function __construct(private AuditLogService $audit) {}

    public function create(string $type, array $data, int $userId): CashTransaction
    {
        return DB::transaction(function () use ($type, $data, $userId) {
            $transaction = CashTransaction::create([
                ...$data,
                'transaction_number' => $this->nextNumber($type, Carbon::parse($data['transaction_date'])),
                'type' => $type,
                'created_by' => $userId,
            ]);
            $this->audit->record('create', 'cash_transaction', $transaction, null, $transaction->toArray());

            return $transaction;
        });
    }

    public function update(CashTransaction $transaction, array $data, int $userId): CashTransaction
    {
        abort_if($transaction->payment_id || $transaction->invoice_id || FactureCommission::where('cash_transaction_id', $transaction->id)->exists(), 422, 'Transaksi kas otomatis tidak dapat diubah manual.');
        $old = $transaction->toArray();
        $transaction->update([...$data, 'updated_by' => $userId]);
        $this->audit->record('update', 'cash_transaction', $transaction, $old, $transaction->fresh()->toArray());

        return $transaction->fresh();
    }

    public function delete(CashTransaction $transaction): void
    {
        abort_if($transaction->payment_id || $transaction->invoice_id || FactureCommission::where('cash_transaction_id', $transaction->id)->exists(), 422, 'Transaksi kas otomatis tidak dapat dihapus manual.');
        $old = $transaction->toArray();
        $transaction->delete();
        $this->audit->record('delete', 'cash_transaction', $transaction, $old);
    }

    public function createFromPayment(Payment $payment): CashTransaction
    {
        return DB::transaction(function () use ($payment) {
            $existing = CashTransaction::where('payment_id', $payment->id)->first();
            if ($existing) {
                return $existing;
            }

            $payment->loadMissing('invoice');
            $transaction = CashTransaction::create([
                'payment_id' => $payment->id,
                'transaction_number' => $this->nextNumber('in', $payment->payment_date),
                'type' => 'in',
                'transaction_date' => $payment->payment_date->toDateString(),
                'category' => 'Pembayaran Invoice',
                'description' => "Pembayaran {$payment->invoice->invoice_number} - {$payment->invoice->billing_name}",
                'payment_method' => $payment->payment_method,
                'amount' => $payment->amount,
                'reference_number' => $payment->reference_number ?: $payment->payment_number,
                'notes' => $payment->notes,
                'created_by' => $payment->created_by,
            ]);
            $this->audit->record('create', 'cash_transaction', $transaction, null, $transaction->toArray());

            return $transaction;
        });
    }

    public function syncFromPayment(Payment $payment, int $userId): CashTransaction
    {
        return DB::transaction(function () use ($payment, $userId) {
            $payment->loadMissing('invoice');
            $transaction = CashTransaction::withTrashed()->where('payment_id', $payment->id)->first();

            if (! $transaction) {
                return $this->createFromPayment($payment);
            }

            $old = $transaction->toArray();
            if ($transaction->trashed()) {
                $transaction->restore();
            }
            $transaction->update([
                'transaction_date' => $payment->payment_date,
                'description' => "Pembayaran {$payment->invoice->invoice_number} - {$payment->invoice->billing_name}",
                'payment_method' => $payment->payment_method,
                'amount' => $payment->amount,
                'reference_number' => $payment->reference_number ?: $payment->payment_number,
                'notes' => $payment->notes,
                'updated_by' => $userId,
            ]);
            $this->audit->record('update', 'cash_transaction', $transaction, $old, $transaction->fresh()->toArray());

            return $transaction->fresh();
        });
    }

    public function syncFactureCommission(FactureCommission $commission, int $userId): CashTransaction
    {
        $commission->loadMissing('document');
        $transaction = CashTransaction::query()->findOrFail($commission->cash_transaction_id);
        $old = $transaction->toArray();
        $transaction->update([
            'transaction_date' => $commission->paid_date,
            'category' => 'Komisi Faktur',
            'description' => 'Pembayaran Komisi Faktur '.$commission->document->facture_number,
            'payment_method' => $commission->payment_method,
            'amount' => $commission->commission_amount,
            'reference_number' => $commission->document->facture_number,
            'notes' => $commission->payment_notes ?: 'Komisi Faktur '.$commission->document->facture_number,
            'updated_by' => $userId,
        ]);
        $this->audit->record('update', 'cash_transaction', $transaction, $old, $transaction->fresh()->toArray());

        return $transaction->fresh();
    }

    public function deleteFactureCommissionCash(CashTransaction $transaction): void
    {
        $old = $transaction->toArray();
        $transaction->delete();
        $this->audit->record('delete', 'cash_transaction', $transaction, $old);
    }

    public function syncInvoiceShipping(Invoice $invoice, int $userId, ?bool $paidNow = null): ?CashTransaction
    {
        return DB::transaction(function () use ($invoice, $userId, $paidNow) {
            $transaction = CashTransaction::withTrashed()->where('invoice_id', $invoice->id)->first();
            $deposit = CourierShippingDeposit::where('invoice_id', $invoice->id)->first();

            if (bccomp((string) $invoice->shipping_cost, '0', 2) <= 0) {
                if ($transaction && ! $transaction->trashed()) {
                    $old = $transaction->toArray();
                    $transaction->delete();
                    $this->audit->record('delete', 'cash_transaction', $transaction, $old);
                }
                $deposit?->delete();

                return null;
            }

            if (! $invoice->courier_id) {
                throw ValidationException::withMessages([
                    'courier_id' => 'Kurir wajib dipilih jika ongkos kirim lebih dari nol.',
                ]);
            }

            if ($paidNow === null) {
                $paidNow = $deposit ? $deposit->paid_at !== null : (bool) $transaction;
            }

            $deposit ??= CourierShippingDeposit::create([
                'invoice_id' => $invoice->id,
                'courier_id' => $invoice->courier_id,
                'amount' => $invoice->shipping_cost,
                'created_by' => $userId,
            ]);
            $deposit->update([
                'courier_id' => $invoice->courier_id,
                'amount' => $invoice->shipping_cost,
            ]);

            if (! $paidNow) {
                if ($transaction && ! $transaction->trashed()) {
                    $old = $transaction->toArray();
                    $transaction->delete();
                    $this->audit->record('delete', 'cash_transaction', $transaction, $old);
                }
                $deposit->update([
                    'paid_at' => null,
                    'paid_by' => null,
                    'cash_transaction_id' => null,
                ]);

                return null;
            }

            $data = [
                'type' => 'out',
                'transaction_date' => today()->toDateString(),
                'category' => 'Ongkos Kirim Invoice',
                'description' => "Ongkos kirim {$invoice->invoice_number} - {$invoice->billing_name}",
                'payment_method' => 'cash',
                'amount' => $invoice->shipping_cost,
                'reference_number' => $invoice->invoice_number,
                'notes' => $invoice->courier_name ? "Kurir: {$invoice->courier_name}" : null,
                'updated_by' => $userId,
            ];

            if ($transaction) {
                $old = $transaction->toArray();
                if ($transaction->trashed()) {
                    $transaction->restore();
                }
                $transaction->update($data);
                $this->audit->record('update', 'cash_transaction', $transaction, $old, $transaction->fresh()->toArray());

                $transaction = $transaction->fresh();
            } else {
                $transaction = CashTransaction::create([
                    ...$data,
                    'invoice_id' => $invoice->id,
                    'transaction_number' => $this->nextNumber('out', today()),
                    'created_by' => $userId,
                ]);
                $this->audit->record('create', 'cash_transaction', $transaction, null, $transaction->toArray());
            }

            $deposit->update([
                'paid_at' => now(),
                'paid_by' => $userId,
                'cash_transaction_id' => $transaction->id,
            ]);

            return $transaction->fresh();
        });
    }

    public function payCourierShippingDeposit(CourierShippingDeposit $deposit, int $userId): CashTransaction
    {
        abort_if($deposit->paid_at, 422, 'Ongkir kurir ini sudah dibayarkan.');

        return $this->syncInvoiceShipping($deposit->invoice()->firstOrFail(), $userId, true);
    }

    public function deleteInvoiceShipping(Invoice $invoice): void
    {
        CourierShippingDeposit::where('invoice_id', $invoice->id)->delete();
        $transaction = CashTransaction::where('invoice_id', $invoice->id)->first();
        if (! $transaction) {
            return;
        }

        $old = $transaction->toArray();
        $transaction->delete();
        $this->audit->record('delete', 'cash_transaction', $transaction, $old);
    }

    private function nextNumber(string $type, CarbonInterface $date): string
    {
        CashTransactionSequence::query()->insertOrIgnore([
            'type' => $type,
            'year' => $date->year,
            'month' => $date->month,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = CashTransactionSequence::query()
            ->where(['type' => $type, 'year' => $date->year, 'month' => $date->month])
            ->lockForUpdate()
            ->firstOrFail();
        $sequence->increment('last_number');

        return sprintf('%s/%04d/%02d/%05d', $type === 'in' ? 'CM' : 'CK', $date->year, $date->month, $sequence->last_number);
    }
}
