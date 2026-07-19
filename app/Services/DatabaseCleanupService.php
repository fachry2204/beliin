<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\CombinedInvoiceDocument;
use App\Models\CourierDelivery;
use App\Models\CourierShippingDeposit;
use App\Models\Customer;
use App\Models\FactureCommission;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DatabaseCleanupService
{
    public const SCOPES = ['customers', 'invoices', 'factures', 'shipping', 'cash_in', 'cash_out'];

    public function __construct(private AuditLogService $audit) {}

    public function counts(): array
    {
        return [
            'customers' => Customer::withTrashed()->count(),
            'invoices' => Invoice::query()->count(),
            'factures' => CombinedInvoiceDocument::query()->count(),
            'shipping' => CourierShippingDeposit::query()->count() + CourierDelivery::query()->count(),
            'cash_in' => CashTransaction::withTrashed()->where('type', 'in')->count(),
            'cash_out' => CashTransaction::withTrashed()->where('type', 'out')->count(),
        ];
    }

    public function purge(string $scope): array
    {
        if (! in_array($scope, self::SCOPES, true)) {
            throw ValidationException::withMessages(['scope' => 'Jenis data yang dipilih tidak valid.']);
        }

        $before = $this->counts();
        [$files, $after] = DB::transaction(function () use ($scope, $before) {
            $files = match ($scope) {
                'customers' => $this->purgeCustomers(),
                'invoices' => $this->purgeInvoices(),
                'factures' => $this->purgeFactures(),
                'shipping' => $this->purgeShipping(),
                'cash_in' => $this->purgeCashIn(),
                'cash_out' => $this->purgeCashOut(),
            };
            $after = $this->counts();
            $this->audit->record('purge', 'database_cleanup', null, ['scope' => $scope, 'counts' => $before], ['counts' => $after]);

            return [$files, $after];
        });

        if ($files !== []) {
            Storage::disk('public')->delete(array_values(array_unique(array_filter($files))));
        }

        Cache::forget('dashboard.metrics');
        Cache::forget('dashboard.metrics.v2');

        return ['before' => $before, 'after' => $after];
    }

    private function purgeCustomers(): array
    {
        if (Invoice::query()->exists()) {
            throw ValidationException::withMessages([
                'scope' => 'Data Client tidak dapat dihapus karena masih ada data invoice. Hapus Data Invoice terlebih dahulu.',
            ]);
        }

        Customer::withTrashed()->forceDelete();

        return [];
    }

    private function purgeInvoices(): array
    {
        if (Invoice::query()->whereHas('delivery', fn ($query) => $query->whereIn('status', [
            CourierDelivery::ACCEPTED,
            CourierDelivery::IN_TRANSIT,
            CourierDelivery::DELIVERED,
        ]))->exists()) {
            throw ValidationException::withMessages([
                'scope' => 'Data Invoice tidak dapat dihapus karena ada kurir yang sudah mengambil atau menjalankan tugas pengiriman. Hapus Data Ongkir terlebih dahulu.',
            ]);
        }

        if (CombinedInvoiceDocument::query()->exists()) {
            throw ValidationException::withMessages([
                'scope' => 'Data Invoice tidak dapat dihapus karena masih ada Faktur. Hapus Data Faktur terlebih dahulu.',
            ]);
        }

        $files = $this->proofFiles();
        $this->deleteCommissionCash();
        CashTransaction::withTrashed()
            ->where(function ($query) {
                $query->whereNotNull('payment_id')->orWhereNotNull('invoice_id');

                if (Schema::hasColumn('cash_transactions', 'combined_invoice_document_id')) {
                    $query->orWhereNotNull('combined_invoice_document_id');
                }
            })
            ->forceDelete();
        FactureCommission::query()->delete();
        Payment::query()->delete();
        DB::table('combined_invoice_document_invoice')->delete();
        CombinedInvoiceDocument::query()->delete();
        Invoice::query()->delete();
        DB::table('combined_invoice_sequences')->delete();
        DB::table('invoice_sequences')->delete();

        return $files;
    }

    private function purgeFactures(): array
    {
        if (Payment::query()->whereNotNull('combined_invoice_document_id')->exists()
            || CombinedInvoiceDocument::query()->where('status', '!=', 'open')->exists()) {
            throw ValidationException::withMessages([
                'scope' => 'Data Faktur tidak dapat dihapus karena sudah memiliki pembayaran. Hapus Data Cash Masuk terlebih dahulu.',
            ]);
        }

        if (CombinedInvoiceDocument::query()->whereHas('invoices.delivery', fn ($query) => $query->whereIn('status', [
            CourierDelivery::ACCEPTED,
            CourierDelivery::IN_TRANSIT,
            CourierDelivery::DELIVERED,
        ]))->exists()) {
            throw ValidationException::withMessages([
                'scope' => 'Data Faktur tidak dapat dihapus karena kurir sudah menjalankan pengiriman pada invoice di dalam Faktur. Hapus Data Ongkir terlebih dahulu.',
            ]);
        }

        $paymentIds = Payment::query()->whereNotNull('combined_invoice_document_id')->pluck('id');
        Payment::query()->whereIn('id', $paymentIds)->update(['combined_invoice_document_id' => null]);
        CashTransaction::withTrashed()->whereIn('payment_id', $paymentIds)->get()->each(function (CashTransaction $cash) {
            $payment = Payment::query()->with('invoice')->find($cash->payment_id);
            if ($payment?->invoice) {
                $cash->update([
                    'description' => "Pembayaran {$payment->invoice->invoice_number} - {$payment->invoice->billing_name}",
                ]);
            }
        });

        $this->deleteCommissionCash();
        if (Schema::hasColumn('cash_transactions', 'combined_invoice_document_id')) {
            CashTransaction::withTrashed()->whereNotNull('combined_invoice_document_id')->forceDelete();
        }
        FactureCommission::query()->delete();
        DB::table('combined_invoice_document_invoice')->delete();
        CombinedInvoiceDocument::query()->delete();
        DB::table('combined_invoice_sequences')->delete();

        return [];
    }

    private function purgeShipping(): array
    {
        $files = CourierDelivery::query()
            ->get(['proof_photo_path', 'departure_photo_path'])
            ->flatMap(fn (CourierDelivery $delivery) => [$delivery->proof_photo_path, $delivery->departure_photo_path])
            ->filter()
            ->all();

        CashTransaction::withTrashed()->where('category', 'Ongkir Driver')->forceDelete();
        CourierShippingDeposit::query()->delete();
        CourierDelivery::query()->delete();
        Invoice::query()->update(['courier_id' => null, 'courier_name' => null, 'shipping_cost' => 0]);
        $factureShippingReset = collect([
            'courier_id' => null,
            'courier_name' => null,
            'shipping_cost' => 0,
        ])->filter(fn ($value, $column) => Schema::hasColumn('combined_invoice_documents', $column))->all();

        if ($factureShippingReset !== []) {
            CombinedInvoiceDocument::query()->update($factureShippingReset);
        }

        return $files;
    }

    private function purgeCashIn(): array
    {
        $files = Payment::query()->whereNotNull('payment_proof')->pluck('payment_proof')->all();
        $this->deleteCommissionCash();
        FactureCommission::query()->delete();
        CashTransaction::withTrashed()->where('type', 'in')->forceDelete();
        Payment::query()->delete();

        Invoice::query()->where('status', '!=', 'draft')->where('status', '!=', 'cancelled')->update([
            'paid_amount' => 0,
            'remaining_amount' => DB::raw('grand_total'),
            'status' => 'unpaid',
        ]);
        CombinedInvoiceDocument::query()->update(['status' => 'open', 'closed_at' => null]);

        return $files;
    }

    private function purgeCashOut(): array
    {
        CashTransaction::withTrashed()->where('type', 'out')->forceDelete();
        CourierShippingDeposit::query()->update(['paid_at' => null, 'cash_transaction_id' => null, 'paid_by' => null]);
        FactureCommission::query()->update([
            'status' => 'unpaid',
            'paid_date' => null,
            'payment_method' => null,
            'payment_notes' => null,
            'cash_transaction_id' => null,
            'paid_by' => null,
            'paid_at' => null,
        ]);

        return [];
    }

    private function deleteCommissionCash(): void
    {
        $cashIds = FactureCommission::query()->whereNotNull('cash_transaction_id')->pluck('cash_transaction_id');
        FactureCommission::query()->whereNotNull('cash_transaction_id')->update(['cash_transaction_id' => null]);
        CashTransaction::withTrashed()->whereIn('id', $cashIds)->forceDelete();
    }

    private function proofFiles(): array
    {
        return [
            ...Payment::query()->whereNotNull('payment_proof')->pluck('payment_proof')->all(),
            ...CourierDelivery::query()->whereNotNull('proof_photo_path')->pluck('proof_photo_path')->all(),
            ...CourierDelivery::query()->whereNotNull('departure_photo_path')->pluck('departure_photo_path')->all(),
        ];
    }
}
