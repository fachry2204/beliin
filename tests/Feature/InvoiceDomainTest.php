<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\CashTransaction;
use App\Models\CombinedInvoiceDocument;
use App\Models\CompanySetting;
use App\Models\Courier;
use App\Models\CourierDelivery;
use App\Models\CourierShippingDeposit;
use App\Models\Customer;
use App\Models\FactureCommission;
use App\Models\IncomingTransaction;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CashTransactionService;
use App\Services\CombinedInvoiceService;
use App\Services\DatabaseCleanupService;
use App\Services\IncomingGoodsService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceNumberService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceDomainTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Courier $courier;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->admin->assignRole('Super Admin');
        $this->customer = Customer::create(['customer_code' => 'CUS-001', 'name' => 'Budi', 'company_name' => 'PT Maju', 'is_active' => true]);
        $this->courier = Courier::create(['courier_code' => 'KUR-BASE', 'name' => 'Bambang Kurir', 'phone' => '081200000001', 'vehicle_type' => 'Motor', 'license_plate' => 'B 1000 KUR', 'is_active' => true]);
        $this->supplier = Supplier::create(['supplier_code' => 'SUP-001', 'name' => 'Sari', 'company_name' => 'PT Sumber', 'is_active' => true]);
        $category = ProductCategory::create(['name' => 'Material', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'sku' => 'SKU-001', 'name' => 'Semen', 'unit' => 'Sak', 'purchase_price' => 60000, 'average_purchase_price' => 60000, 'selling_price' => 100000, 'stock' => 100, 'minimum_stock' => 5, 'is_active' => true]);
    }

    public function test_authorized_user_can_create_customer_supplier_and_product(): void
    {
        $this->actingAs($this->admin)->post(route('customers.store'), ['customer_code' => 'CUS-002', 'name' => 'Ani', 'is_active' => true])->assertRedirect();
        $this->actingAs($this->admin)->post(route('suppliers.store'), ['supplier_code' => 'SUP-002', 'name' => 'Dani', 'is_active' => true])->assertRedirect();
        $this->actingAs($this->admin)->post(route('products.store'), ['category_id' => $this->product->category_id, 'sku' => 'SKU-002', 'name' => 'Pasir', 'unit' => 'M3', 'purchase_price' => 10000, 'selling_price' => 15000, 'minimum_stock' => 0, 'is_active' => true])->assertRedirect();
        $this->assertDatabaseHas('customers', ['customer_code' => 'CUS-002']);
        $this->assertDatabaseHas('suppliers', ['supplier_code' => 'SUP-002']);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-002']);
    }

    public function test_authorized_user_can_manage_couriers(): void
    {
        $this->actingAs($this->admin)->post(route('couriers.store'), [
            'courier_code' => 'KUR-001',
            'name' => 'Andi Kurir',
            'phone' => '081234567890',
            'vehicle_type' => 'Motor',
            'license_plate' => 'B 1234 KUR',
            'notes' => 'Area Jakarta',
            'is_active' => true,
        ])->assertRedirect();

        $courier = Courier::where('courier_code', 'KUR-001')->firstOrFail();
        $this->assertDatabaseHas('couriers', ['id' => $courier->id, 'name' => 'Andi Kurir']);

        $this->actingAs($this->admin)->put(route('couriers.update', $courier), [
            'courier_code' => 'KUR-001',
            'name' => 'Andi Pratama',
            'phone' => '081234567890',
            'vehicle_type' => 'Mobil Box',
            'license_plate' => 'B 1234 KUR',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('couriers', ['id' => $courier->id, 'name' => 'Andi Pratama', 'vehicle_type' => 'Mobil Box']);
        $this->actingAs($this->admin)->delete(route('couriers.destroy', $courier))->assertRedirect();
        $this->assertSoftDeleted('couriers', ['id' => $courier->id]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'courier', 'action' => 'delete']);
    }

    public function test_audit_log_page_loads_with_the_related_user(): void
    {
        $this->actingAs($this->admin);
        app(AuditLogService::class)->record('view', 'audit_test', $this->customer);

        $this->get(route('activity.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Audit')
                ->where('rows.data.0.user.id', $this->admin->id)
                ->where('rows.data.0.user.name', $this->admin->name));
    }

    public function test_authorized_user_can_manage_cash_in_and_cash_out(): void
    {
        $cashIn = [
            'transaction_date' => '2026-07-15',
            'category' => 'Penjualan Tunai',
            'description' => 'Penerimaan penjualan harian',
            'payment_method' => 'cash',
            'amount' => 1500000,
            'reference_number' => 'REF-IN-001',
        ];
        $cashOut = [
            'transaction_date' => '2026-07-15',
            'category' => 'Operasional',
            'description' => 'Biaya transportasi',
            'payment_method' => 'transfer',
            'amount' => 350000,
            'reference_number' => 'REF-OUT-001',
        ];

        $this->actingAs($this->admin)->post(route('cash-in.store'), $cashIn)->assertRedirect();
        $this->actingAs($this->admin)->post(route('cash-out.store'), $cashOut)->assertRedirect();
        $this->assertDatabaseHas('cash_transactions', ['transaction_number' => 'CM/2026/07/00001', 'type' => 'in', 'amount' => 1500000]);
        $this->assertDatabaseHas('cash_transactions', ['transaction_number' => 'CK/2026/07/00001', 'type' => 'out', 'amount' => 350000]);

        $transaction = CashTransaction::where('type', 'out')->firstOrFail();
        $cashOut['amount'] = 400000;
        $this->actingAs($this->admin)->put(route('cash-out.update', $transaction), $cashOut)->assertRedirect();
        $this->assertDatabaseHas('cash_transactions', ['id' => $transaction->id, 'amount' => 400000]);
        $this->actingAs($this->admin)->delete(route('cash-out.destroy', $transaction))->assertRedirect();
        $this->assertSoftDeleted('cash_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'cash_transaction', 'action' => 'delete']);
    }

    public function test_staff_cannot_manage_products_or_view_profit_report(): void
    {
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $staff->assignRole('Staff');
        $this->actingAs($staff)->post(route('products.store'), ['category_id' => $this->product->category_id])->assertForbidden();
        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
    }

    public function test_report_center_and_each_report_support_search_and_date_filters(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->update([
            'status' => InvoiceStatus::Unpaid,
            'issued_at' => now(),
            'shipping_cost' => 50000,
        ]);
        $facture = app(CombinedInvoiceService::class)->create($this->customer, [$invoice->id], '2026-07-24', null, 0, $this->admin->id);
        $factureDate = $facture->opened_at->toDateString();
        FactureCommission::create([
            'combined_invoice_document_id' => $facture->id,
            'facture_payment_date' => '2026-07-15',
            'commission_base' => 'margin',
            'commission_type' => 'nominal',
            'commission_value' => 25000,
            'base_amount' => 250000,
            'facture_total' => 1000000,
            'margin_total' => 250000,
            'commission_amount' => 25000,
            'status' => 'unpaid',
            'created_by' => $this->admin->id,
        ]);
        app(CashTransactionService::class)->create('in', [
            'transaction_date' => '2026-07-15',
            'category' => 'Pembayaran Pelanggan',
            'description' => 'Kas masuk pengujian laporan',
            'payment_method' => 'cash',
            'amount' => 500000,
            'reference_number' => 'REPORT-IN',
        ], $this->admin->id);
        app(CashTransactionService::class)->create('out', [
            'transaction_date' => '2026-07-16',
            'category' => 'Operasional',
            'description' => 'Kas keluar pengujian laporan',
            'payment_method' => 'transfer',
            'amount' => 125000,
            'reference_number' => 'REPORT-OUT',
        ], $this->admin->id);

        $this->actingAs($this->admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Home')
                ->where('canViewProfit', true));

        $filters = ['date_from' => '2026-07-15', 'date_to' => '2026-07-15', 'search' => $invoice->invoice_number];
        $this->get(route('reports.invoices', $filters))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Invoices')
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $invoice->id)
                ->where('filters.search', $invoice->invoice_number));

        $this->get(route('reports.combined-invoices', [
            'date_from' => $factureDate,
            'date_to' => $factureDate,
            'search' => $facture->facture_number,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/CombinedInvoices')
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $facture->id)
                ->where('summary.invoice_count', 1));

        $this->get(route('reports.cash', [
            'date_from' => '2026-07-15',
            'date_to' => '2026-07-15',
            'search' => 'REPORT-IN',
            'type' => 'in',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Cash')
            ->has('rows.data', 1)
            ->where('rows.data.0.type', 'in')
            ->where('summary.incoming_total', 500000));

        $this->get(route('reports.margins', [
            'date_from' => $factureDate,
            'date_to' => $factureDate,
            'search' => $facture->facture_number,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Margins')
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $facture->id)
                ->where('rows.data.0.facture_number', $facture->facture_number)
                ->where('rows.data.0.gross_margin_total', 250000)
                ->where('rows.data.0.commission_total', 25000)
                ->where('rows.data.0.shipping_total', 50000)
                ->where('summary.facture_count', 1)
                ->where('summary.gross_margin_total', '250000')
                ->where('summary.commission_total', '25000')
                ->where('summary.shipping_total', '50000')
                ->where('summary.net_margin_total', '175000'));

        $limitedUser = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $limitedUser->givePermissionTo('reports.view');
        $this->actingAs($limitedUser)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canViewProfit', false));
        $this->get(route('reports.margins'))->assertForbidden();
    }

    public function test_invoice_index_shows_margin_only_to_users_with_profit_permission(): void
    {
        $invoice = $this->makeInvoice();
        CourierDelivery::create([
            'invoice_id' => $invoice->id,
            'courier_id' => $this->courier->id,
            'status' => CourierDelivery::IN_TRANSIT,
        ]);

        $this->actingAs($this->admin)->get(route('invoices.index'))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->where('canViewProfit', true)
                ->where('rows.data.0.gross_profit', '250000.00')
                ->where('rows.data.0.delivery.status', CourierDelivery::IN_TRANSIT)
        );

        $staff = User::factory()->create(['email_verified_at' => now()]);
        $staff->assignRole('Staff');
        $this->actingAs($staff)->get(route('invoices.index'))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->where('canViewProfit', false)
                ->missing('rows.data.0.gross_profit')
                ->missing('rows.data.0.total_cost')
        );
    }

    public function test_only_admin_can_delete_a_draft_invoice(): void
    {
        $invoice = $this->makeInvoice();
        $finance = User::factory()->create(['email_verified_at' => now()]);
        $finance->assignRole('Finance');

        $this->actingAs($finance)->delete(route('invoices.destroy', $invoice))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_cancelling_an_invoice_removes_payment_history_and_linked_cash_in(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now()]);
        $payment = app(PaymentService::class)->record($invoice, [
            'payment_date' => today()->toDateString(),
            'amount' => 400000,
            'payment_method' => 'transfer',
        ], $this->admin->id);
        $cashTransaction = CashTransaction::where('payment_id', $payment->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('invoices.cancel', $invoice))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->remaining_amount);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('cash_transactions', ['id' => $cashTransaction->id]);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'invoice',
            'action' => 'cancel',
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_invoice_cannot_be_cancelled_or_deleted_after_delivery_starts_or_facture_is_created(): void
    {
        $deliveryInvoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($deliveryInvoice, $this->admin->id, false, $this->courier->id, 15000);
        $deliveryInvoice->delivery()->update([
            'status' => CourierDelivery::ACCEPTED,
            'accepted_at' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('invoices.cancel', $deliveryInvoice))->assertStatus(422);
        $this->actingAs($this->admin)->delete(route('invoices.destroy', $deliveryInvoice))->assertStatus(422);
        $this->assertDatabaseHas('invoices', ['id' => $deliveryInvoice->id, 'status' => 'unpaid']);

        $facture = app(CombinedInvoiceService::class)->create(
            $this->customer,
            [$deliveryInvoice->id],
            null,
            $this->courier->id,
            15000,
            $this->admin->id,
        );

        $this->get(route('invoices.show', $deliveryInvoice))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('destructiveLockReason', fn ($reason) => str_contains($reason, 'kurir sudah mengambil') && str_contains($reason, $facture->facture_number))
        );
        $this->delete(route('combined-invoices.destroy', $facture))->assertStatus(422);
        $this->get(route('combined-invoices.show', $facture))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('deletionLocked', true)
                ->where('deletionLockReason', fn ($reason) => str_contains($reason, 'kurir sudah mengambil'))
        );

        $factureOnlyInvoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($factureOnlyInvoice, $this->admin->id, false, $this->courier->id, 0);
        $factureOnly = app(CombinedInvoiceService::class)->create(
            $this->customer,
            [$factureOnlyInvoice->id],
            null,
            null,
            0,
            $this->admin->id,
        );

        $this->post(route('invoices.cancel', $factureOnlyInvoice))->assertStatus(422);
        $this->delete(route('invoices.destroy', $factureOnlyInvoice))->assertStatus(422);
        $this->assertDatabaseHas('combined_invoice_documents', ['id' => $factureOnly->id]);
    }

    public function test_client_cannot_be_deleted_while_it_has_invoices(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->from(route('customers.index'))
            ->delete(route('customers.destroy', $this->customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);

        $unusedCustomer = Customer::factory()->create();
        $this->delete(route('customers.destroy', $unusedCustomer))->assertRedirect();
        $this->assertSoftDeleted('customers', ['id' => $unusedCustomer->id]);
    }

    public function test_admin_can_delete_a_cancelled_invoice_with_legacy_payment_data(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now()]);
        $payment = app(PaymentService::class)->record($invoice, [
            'payment_date' => today()->toDateString(),
            'amount' => 250000,
            'payment_method' => 'cash',
        ], $this->admin->id);
        $cashTransaction = CashTransaction::where('payment_id', $payment->id)->firstOrFail();
        $invoice->update(['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now()]);

        $this->actingAs($this->admin)
            ->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('cash_transactions', ['id' => $cashTransaction->id]);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'invoice',
            'action' => 'delete',
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_admin_can_delete_an_unpaid_invoice_and_its_payment_history(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now()]);
        $payment = app(PaymentService::class)->record($invoice, [
            'payment_date' => today()->toDateString(),
            'amount' => 250000,
            'payment_method' => 'cash',
        ], $this->admin->id);
        $cashTransaction = CashTransaction::where('payment_id', $payment->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('cash_transactions', ['id' => $cashTransaction->id]);
    }

    public function test_finalizing_incoming_goods_increases_stock_and_records_movement(): void
    {
        $transaction = app(IncomingGoodsService::class)->create(['supplier_id' => $this->supplier->id, 'transaction_date' => today()->toDateString(), 'items' => [['product_id' => $this->product->id, 'purchase_price' => '70000', 'quantity' => '10', 'volume' => '1', 'calculation_method' => 'qty']]], $this->admin->id);
        app(IncomingGoodsService::class)->finalize($transaction, $this->admin->id);
        $this->assertSame('110.0000', $this->product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->product->id, 'movement_type' => 'IN', 'stock_after' => 110]);
        $this->assertSame('final', IncomingTransaction::find($transaction->id)->status->value);
    }

    public function test_invoice_calculation_supports_discounts_and_tax_without_charging_shipping(): void
    {
        $service = app(InvoiceCalculationService::class);
        $base = ['tax_percentage' => 11, 'shipping_cost' => 300000, 'items' => [['product_id' => 1, 'purchase_price' => 60000, 'selling_price' => 100000, 'quantity' => 100, 'volume' => 1, 'calculation_method' => 'qty']]];
        $percentage = $service->calculate([...$base, 'discount_type' => 'percentage', 'discount_value' => 10]);
        $this->assertSame('10000000.00', $percentage['subtotal']);
        $this->assertSame('1000000.00', $percentage['discount_amount']);
        $this->assertSame('990000.00', $percentage['tax_amount']);
        $this->assertSame('9990000.00', $percentage['grand_total']);
        $nominal = $service->calculate([...$base, 'discount_type' => 'nominal', 'discount_value' => 500000]);
        $this->assertSame('500000', $nominal['discount_amount']);
        $this->assertSame('10545000.00', $nominal['grand_total']);
    }

    public function test_invoice_shipping_can_be_saved_as_unpaid_courier_deposit_and_paid_later(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'invoice_date' => '2026-07-15',
            'due_date' => '2026-07-22',
            'discount_type' => 'nominal',
            'discount_value' => 0,
            'tax_percentage' => 0,
            'shipping_cost' => 300000,
            'items' => [[
                'product_id' => $this->product->id,
                'purchase_price' => 60000,
                'selling_price' => 100000,
                'quantity' => 2,
                'volume' => 1,
                'calculation_method' => 'qty',
            ]],
        ];
        $invoice = app(InvoiceService::class)->create($data, $this->admin->id);

        $this->assertSame('300000.00', $invoice->shipping_cost);
        $this->assertSame('200000.00', $invoice->grand_total);
        $this->assertDatabaseMissing('cash_transactions', ['invoice_id' => $invoice->id]);
        $this->assertDatabaseMissing('courier_shipping_deposits', ['invoice_id' => $invoice->id]);

        $data['shipping_cost'] = 150000;
        app(InvoiceService::class)->updateDraft($invoice, $data, $this->admin->id);
        app(InvoiceService::class)->issue($invoice->fresh(), $this->admin->id, false);

        $deposit = CourierShippingDeposit::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame($this->courier->id, $deposit->courier_id);
        $this->assertSame('150000.00', $deposit->amount);
        $this->assertNull($deposit->paid_at);
        $this->assertDatabaseMissing('cash_transactions', ['invoice_id' => $invoice->id]);

        $this->actingAs($this->admin)
            ->get(route('couriers.show', $this->courier))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Couriers/Show')
                ->where('deposits.data.0.invoice.invoice_number', $invoice->invoice_number)
                ->where('deposits.data.0.amount', '150000.00')
                ->where('deposits.data.0.paid_at', null)
                ->where('deposits.data.0.invoice.delivery.status', CourierDelivery::PENDING));

        $this->actingAs($this->admin)
            ->post(route('couriers.shipping-deposits.pay', [$this->courier, $deposit]))
            ->assertRedirect();

        $shippingCash = CashTransaction::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('out', $shippingCash->type);
        $this->assertSame('150000.00', $shippingCash->amount);
        $this->assertSame('Ongkir Driver', $shippingCash->category);
        $this->assertNotNull($deposit->fresh()->paid_at);

        $this->get(route('couriers.show', $this->courier))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('deposits.data.0.invoice.delivery.status', CourierDelivery::PENDING)
                ->where('deposits.data.0.paid_at', fn ($value) => filled($value))
        );

        $this->actingAs($this->admin)
            ->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertDontSee('Biaya kirim')
            ->assertDontSee('Biaya Kirim')
            ->assertDontSee('Ongkos Kirim');

        app(InvoiceService::class)->cancel($invoice->fresh(), $this->admin->id);
        $this->assertSoftDeleted('cash_transactions', ['id' => $shippingCash->id]);
        $this->assertDatabaseMissing('courier_shipping_deposits', ['id' => $deposit->id]);
    }

    public function test_courier_and_shipping_are_selected_when_invoice_is_issued(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->update(['courier_id' => null, 'courier_name' => null, 'shipping_cost' => 0]);

        $this->actingAs($this->admin)
            ->get(route('invoices.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Create')
                ->missing('couriers'));

        $this->actingAs($this->admin)
            ->post(route('invoices.issue', $invoice), [
                'courier_id' => $this->courier->id,
                'shipping_cost' => 175000,
                'shipping_paid_now' => false,
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertSame($this->courier->id, $invoice->courier_id);
        $this->assertSame('Bambang Kurir', $invoice->courier_name);
        $this->assertSame('175000.00', $invoice->shipping_cost);
        $this->assertDatabaseCount('combined_invoice_documents', 0);
        $this->assertDatabaseHas('courier_shipping_deposits', [
            'invoice_id' => $invoice->id,
            'courier_id' => $this->courier->id,
            'amount' => 175000,
            'paid_at' => null,
        ]);
    }

    public function test_invoice_shipping_paid_when_issued_is_recorded_directly_as_cash_out(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->update(['shipping_cost' => 125000]);

        app(InvoiceService::class)->issue($invoice->fresh(), $this->admin->id, true);

        $this->assertDatabaseHas('cash_transactions', [
            'invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => 125000,
        ]);
        $this->assertDatabaseHas('courier_shipping_deposits', [
            'invoice_id' => $invoice->id,
            'courier_id' => $this->courier->id,
            'amount' => 125000,
        ]);
        $this->assertNotNull($invoice->shippingDeposit()->firstOrFail()->paid_at);
        $this->actingAs($this->admin)->get(route('couriers.show', $this->courier))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('deposits.data.0.invoice.delivery.status', CourierDelivery::PENDING)
                ->where('deposits.data.0.paid_at', fn ($value) => filled($value))
        );
    }

    public function test_shipping_can_be_edited_after_invoice_is_issued_and_cash_out_is_synchronized(): void
    {
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false);
        $replacement = Courier::create([
            'courier_code' => 'KUR-PENGGANTI',
            'name' => 'Kurir Pengganti',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('invoices.shipping.update', $invoice), [
                'courier_id' => $replacement->id,
                'shipping_cost' => 85000,
                'shipping_paid_now' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame($replacement->id, $invoice->courier_id);
        $this->assertSame('Kurir Pengganti', $invoice->courier_name);
        $this->assertSame('85000.00', $invoice->shipping_cost);
        $this->assertDatabaseHas('courier_deliveries', [
            'invoice_id' => $invoice->id,
            'courier_id' => $replacement->id,
            'status' => CourierDelivery::PENDING,
        ]);
        $this->assertDatabaseHas('courier_shipping_deposits', [
            'invoice_id' => $invoice->id,
            'courier_id' => $replacement->id,
            'amount' => 85000,
        ]);
        $cashOut = CashTransaction::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('85000.00', $cashOut->amount);
        $this->assertNotNull($invoice->shippingDeposit()->firstOrFail()->paid_at);

        $this->actingAs($this->admin)
            ->put(route('invoices.shipping.update', $invoice), [
                'courier_id' => $replacement->id,
                'shipping_cost' => 90000,
                'shipping_paid_now' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('cash_transactions', ['id' => $cashOut->id]);
        $deposit = $invoice->shippingDeposit()->firstOrFail();
        $this->assertSame('90000.00', $deposit->amount);
        $this->assertNull($deposit->paid_at);
        $this->assertNull($deposit->cash_transaction_id);
    }

    public function test_courier_cannot_be_changed_after_delivery_task_is_accepted(): void
    {
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false);
        $originalShippingCost = $invoice->fresh()->shipping_cost;
        $invoice->delivery()->update(['status' => CourierDelivery::ACCEPTED]);
        $replacement = Courier::create([
            'courier_code' => 'KUR-TERKUNCI',
            'name' => 'Kurir Tidak Terpilih',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->from(route('invoices.show', $invoice))
            ->put(route('invoices.shipping.update', $invoice), [
                'courier_id' => $replacement->id,
                'shipping_cost' => 75000,
                'shipping_paid_now' => false,
            ])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasErrors([
                'courier_id' => 'Kurir tidak dapat diganti setelah tugas diambil.',
            ]);

        $invoice->refresh();
        $this->assertSame($this->courier->id, $invoice->courier_id);
        $this->assertSame($originalShippingCost, $invoice->shipping_cost);
    }

    public function test_admin_can_edit_invoice_delivery_status_and_timeline(): void
    {
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false);

        $this->actingAs($this->admin)
            ->put(route('invoices.shipping.update', $invoice), [
                'courier_id' => $this->courier->id,
                'shipping_cost' => 50000,
                'shipping_paid_now' => false,
                'delivery_status' => CourierDelivery::IN_TRANSIT,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $delivery = $invoice->delivery()->firstOrFail();
        $this->assertSame(CourierDelivery::IN_TRANSIT, $delivery->status);
        $this->assertNotNull($delivery->accepted_at);
        $this->assertNotNull($delivery->departed_at);
        $this->assertNull($delivery->delivered_at);

        $this->actingAs($this->admin)
            ->put(route('invoices.shipping.update', $invoice), [
                'courier_id' => $this->courier->id,
                'shipping_cost' => 50000,
                'shipping_paid_now' => false,
                'delivery_status' => CourierDelivery::PENDING,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $delivery->refresh();
        $this->assertSame(CourierDelivery::PENDING, $delivery->status);
        $this->assertNull($delivery->accepted_at);
        $this->assertNull($delivery->departed_at);
        $this->assertNull($delivery->delivered_at);
    }

    public function test_invalid_invoice_delivery_status_is_rejected(): void
    {
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false);

        $this->actingAs($this->admin)
            ->from(route('invoices.show', $invoice))
            ->put(route('invoices.shipping.update', $invoice), [
                'courier_id' => $this->courier->id,
                'shipping_cost' => 50000,
                'shipping_paid_now' => false,
                'delivery_status' => 'status-tidak-valid',
            ])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasErrors('delivery_status');
    }

    public function test_company_settings_can_be_saved_through_method_spoofed_post(): void
    {
        $invalidSettings = [
            '_method' => 'put',
            'company_name' => 'PT Pengaturan Baru',
            'invoice_prefix' => 'INV',
            'default_tax_percentage' => 10.5,
            'commission_margin_warning_percentage' => 101,
            'tax_enabled' => true,
            'discount_enabled' => false,
            'printer_type' => 'unknown',
            'printer_paper_size' => 'receipt',
            'printer_orientation' => 'diagonal',
        ];
        $this->actingAs($this->admin)
            ->post(route('company.update'), $invalidSettings)
            ->assertSessionHasErrors(['default_tax_percentage', 'commission_margin_warning_percentage', 'printer_type', 'printer_paper_size', 'printer_orientation']);

        $this->actingAs($this->admin)
            ->post(route('company.update'), [
                '_method' => 'put',
                'company_name' => 'PT Pengaturan Baru',
                'invoice_prefix' => 'INV',
                'default_tax_percentage' => 11,
                'commission_margin_warning_percentage' => 8,
                'tax_enabled' => true,
                'discount_enabled' => false,
                'printer_type' => 'laser',
                'printer_paper_size' => 'a4',
                'printer_orientation' => 'landscape',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('company_settings', [
            'company_name' => 'PT Pengaturan Baru',
            'tax_enabled' => true,
            'discount_enabled' => false,
            'commission_margin_warning_percentage' => 8,
            'printer_type' => 'laser',
            'printer_paper_size' => 'a4',
            'printer_orientation' => 'landscape',
        ]);
    }

    public function test_company_favicon_is_saved_and_used_by_the_installable_app_manifest(): void
    {
        Storage::fake('public');
        $favicon = UploadedFile::fake()->image('app-icon.png', 512, 512);

        $this->actingAs($this->admin)
            ->post(route('company.update'), [
                '_method' => 'put',
                'company_name' => 'PT Ikon Dinamis',
                'favicon' => $favicon,
                'invoice_prefix' => 'INV',
                'default_tax_percentage' => 11,
                'commission_margin_warning_percentage' => 10,
                'tax_enabled' => true,
                'discount_enabled' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $setting = CompanySetting::firstOrFail();
        $this->assertNotNull($setting->favicon);
        Storage::disk('public')->assertExists($setting->favicon);

        $this->get(route('app.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'PT Ikon Dinamis')
            ->assertJsonPath('short_name', 'PT Ikon Dinamis')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('icons.0.type', 'image/png');

        $this->actingAs($this->admin)
            ->get(route('company.edit'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('rel="apple-touch-icon"', false);
    }

    public function test_company_settings_exposes_role_access_and_super_admin_can_update_it(): void
    {
        $this->actingAs($this->admin)
            ->get(route('company.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Company')
                ->has('roleAccess.groups')
                ->where('roleAccess.roles', fn ($roles) => collect($roles)->contains(fn ($role) => $role['name'] === 'Finance')));

        $finance = Role::findByName('Finance');
        $this->actingAs($this->admin)
            ->put(route('company.roles.update', $finance), [
                'permissions' => ['reports.export', 'cash.manage'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $finance->refresh();
        $this->assertTrue($finance->hasPermissionTo('reports.export'));
        $this->assertTrue($finance->hasPermissionTo('reports.view'));
        $this->assertTrue($finance->hasPermissionTo('cash.manage'));
        $this->assertTrue($finance->hasPermissionTo('cash.view'));
        $this->assertFalse($finance->hasPermissionTo('customers.view'));
        $this->assertDatabaseHas('activity_logs', ['module' => 'role_access', 'action' => 'update']);
    }

    public function test_role_access_rejects_unknown_permissions_and_protects_super_admin(): void
    {
        $finance = Role::findByName('Finance');
        $this->actingAs($this->admin)
            ->put(route('company.roles.update', $finance), ['permissions' => ['system.owner']])
            ->assertSessionHasErrors('permissions.0');

        $superAdmin = Role::findByName('Super Admin');
        $this->actingAs($this->admin)
            ->put(route('company.roles.update', $superAdmin), ['permissions' => []])
            ->assertStatus(422);

        $this->assertTrue($superAdmin->fresh()->hasPermissionTo('settings.manage'));
    }

    public function test_disabled_tax_and_discount_are_forced_to_zero_on_invoice(): void
    {
        CompanySetting::create([
            'company_name' => 'PT Tanpa Pajak',
            'invoice_prefix' => 'INV',
            'default_tax_percentage' => 11,
            'tax_enabled' => false,
            'discount_enabled' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('invoices.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('taxEnabled', false)
                ->where('discountEnabled', false));

        $invoice = app(InvoiceService::class)->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-07-15',
            'due_date' => '2026-07-22',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'tax_percentage' => 11,
            'items' => [[
                'product_id' => $this->product->id,
                'purchase_price' => 60000,
                'selling_price' => 100000,
                'quantity' => 2,
                'volume' => 1,
                'calculation_method' => 'qty',
            ]],
        ], $this->admin->id);

        $this->assertSame('0.00', $invoice->discount_amount);
        $this->assertSame(0, $invoice->tax_percentage);
        $this->assertSame('0.00', $invoice->tax_amount);
        $this->assertSame('200000.00', $invoice->grand_total);
    }

    public function test_invoice_calculation_is_always_based_on_quantity(): void
    {
        $result = app(InvoiceCalculationService::class)->calculate(['discount_type' => 'nominal', 'discount_value' => 0, 'tax_percentage' => 0, 'shipping_cost' => 0, 'items' => [['purchase_price' => 10, 'selling_price' => 25, 'quantity' => 4, 'volume' => 10, 'calculation_method' => 'qty_volume']]]);
        $this->assertSame('100.00', $result['subtotal']);
        $this->assertSame('40.00', $result['total_cost']);
    }

    public function test_invoice_numbers_are_unique_sequential_and_never_count_based(): void
    {
        $numbers = app(InvoiceNumberService::class);
        $date = Carbon::parse('2026-07-15');
        $this->assertSame('INV/2026/07/00001', $numbers->next($date));
        $this->assertSame('INV/2026/07/00002', $numbers->next($date));
        $this->assertDatabaseHas('invoice_sequences', ['year' => 2026, 'month' => 7, 'last_number' => 2]);
    }

    public function test_invoice_percentage_fields_only_accept_whole_numbers_up_to_one_hundred(): void
    {
        $this->actingAs($this->admin)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'invoice_date' => '2026-07-15',
            'due_date' => '2026-07-22',
            'discount_type' => 'percentage',
            'discount_value' => 101,
            'tax_percentage' => 10.5,
            'shipping_cost' => 0,
            'items' => [[
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'unit' => $this->product->unit,
                'purchase_price' => 75000,
                'selling_price' => 100000,
                'quantity' => 1,
            ]],
        ])->assertSessionHasErrors(['discount_value', 'tax_percentage']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_creation_recalculates_values_and_creates_audit_log(): void
    {
        $invoice = $this->makeInvoice();
        $this->assertSame('1000000.00', $invoice->grand_total);
        $this->assertSame(1, $invoice->items->first()->volume);
        $this->assertSame('qty', $invoice->items->first()->calculation_method);
        $this->assertSame(75000, $invoice->items->first()->purchase_price);
        $this->assertSame('750000.00', $invoice->total_cost);
        $this->assertSame('250000.00', $invoice->gross_profit);
        $this->assertSame($this->courier->id, $invoice->courier_id);
        $this->assertSame('Bambang Kurir', $invoice->courier_name);
        $this->assertDatabaseHas('activity_logs', ['module' => 'invoice', 'action' => 'create']);
    }

    public function test_invoice_accepts_a_manual_item_without_a_product_record(): void
    {
        $invoice = app(InvoiceService::class)->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-07-15',
            'due_date' => '2026-07-30',
            'discount_type' => 'nominal',
            'discount_value' => 0,
            'tax_percentage' => 0,
            'shipping_cost' => 0,
            'items' => [[
                'product_id' => null,
                'product_name' => 'Jasa Instalasi',
                'sku' => 'JS-001',
                'unit' => 'Paket',
                'purchase_price' => 200000,
                'selling_price' => 350000,
                'quantity' => 2,
            ]],
        ], $this->admin->id);

        $item = $invoice->items->first();
        $this->assertNull($item->product_id);
        $this->assertSame('Jasa Instalasi', $item->product_name_snapshot);
        $this->assertSame('Paket', $item->unit_snapshot);
        $this->assertSame('700000.00', $invoice->grand_total);
    }

    public function test_issuing_an_invoice_does_not_change_product_stock(): void
    {
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false);

        $this->assertSame('100.0000', $this->product->fresh()->stock);
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => Invoice::class, 'reference_id' => $invoice->id]);
    }

    public function test_partial_and_full_payment_update_status_and_balance(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now()]);
        $payments = app(PaymentService::class);
        $payments->record($invoice, ['payment_date' => today()->toDateString(), 'amount' => 400000, 'payment_method' => 'transfer'], $this->admin->id);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertSame('600000.00', $invoice->fresh()->remaining_amount);
        $partialCash = CashTransaction::whereNotNull('payment_id')->firstOrFail();
        $this->assertSame('400000.00', $partialCash->amount);
        $this->assertSame('Pembayaran Invoice', $partialCash->category);
        $this->assertStringContainsString($invoice->invoice_number, $partialCash->description);
        $payments->record($invoice->fresh(), ['payment_date' => today()->toDateString(), 'amount' => 600000, 'payment_method' => 'cash'], $this->admin->id);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->remaining_amount);
        $this->assertSame(2, CashTransaction::whereNotNull('payment_id')->count());
        $this->assertEquals(1000000, CashTransaction::whereNotNull('payment_id')->sum('amount'));

        app(CashTransactionService::class)->createFromPayment($partialCash->payment);
        $this->assertSame(2, CashTransaction::whereNotNull('payment_id')->count());
        $this->actingAs($this->admin)->delete(route('cash-in.destroy', $partialCash))->assertStatus(422);
    }

    public function test_issued_and_partially_paid_invoices_can_be_edited_safely(): void
    {
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false);
        $data = [
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'invoice_date' => '2026-07-15',
            'due_date' => '2026-07-30',
            'discount_type' => 'nominal',
            'discount_value' => 0,
            'tax_percentage' => 0,
            'shipping_cost' => 0,
            'items' => [[
                'product_id' => $this->product->id,
                'purchase_price' => 75000,
                'selling_price' => 120000,
                'quantity' => 10,
                'volume' => 1,
                'calculation_method' => 'qty',
            ]],
        ];

        $updated = app(InvoiceService::class)->updateDraft($invoice->fresh(), $data, $this->admin->id);
        $this->assertSame(InvoiceStatus::Unpaid, $updated->status);
        $this->assertSame('1200000.00', $updated->remaining_amount);

        app(PaymentService::class)->record($updated, [
            'payment_date' => today()->toDateString(),
            'amount' => 400000,
            'payment_method' => 'transfer',
        ], $this->admin->id);
        $data['items'][0]['selling_price'] = 110000;
        $updated = app(InvoiceService::class)->updateDraft($updated->fresh(), $data, $this->admin->id);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $updated->status);
        $this->assertSame('700000.00', $updated->remaining_amount);

        $this->actingAs($this->admin)
            ->get(route('invoices.show', $updated))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEditInvoice', true)
                ->where('canDeleteInvoice', true));
        $this->get(route('invoices.edit', $updated))->assertOk();

        $data['items'][0]['selling_price'] = 30000;
        $this->expectException(ValidationException::class);
        app(InvoiceService::class)->updateDraft($updated->fresh(), $data, $this->admin->id);
    }

    public function test_invoice_rejects_selling_price_equal_to_or_below_purchase_price(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'invoice_date' => '2026-07-15',
            'due_date' => '2026-07-22',
            'discount_type' => 'nominal',
            'discount_value' => 0,
            'tax_percentage' => 0,
            'shipping_cost' => 0,
            'items' => [[
                'product_id' => $this->product->id,
                'purchase_price' => 75000,
                'selling_price' => 75000,
                'quantity' => 1,
                'volume' => 1,
                'calculation_method' => 'qty',
            ]],
        ];

        foreach ([75000, 70000] as $sellingPrice) {
            $data['items'][0]['selling_price'] = $sellingPrice;

            try {
                app(InvoiceService::class)->create($data, $this->admin->id);
                $this->fail('Invoice dengan harga jual yang tidak valid seharusnya ditolak.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'Harga jual harus lebih besar dari harga beli.',
                    $exception->errors()['items.0.selling_price'][0],
                );
            }
        }
    }

    public function test_empty_shipping_cost_is_normalized_to_zero_when_updating_invoice(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->put(route('invoices.update', $invoice), [
                'customer_id' => $this->customer->id,
                'courier_id' => $this->courier->id,
                'invoice_date' => '2026-07-15',
                'due_date' => '2026-07-22',
                'discount_type' => 'nominal',
                'discount_value' => 0,
                'tax_percentage' => 0,
                'shipping_cost' => '',
                'items' => [[
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'unit' => $this->product->unit,
                    'purchase_price' => 75000,
                    'selling_price' => 100000,
                    'quantity' => 10,
                ]],
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame('0.00', $invoice->fresh()->shipping_cost);
    }

    public function test_combined_invoice_is_created_manually_from_selected_outstanding_invoices(): void
    {
        $unpaid = $this->makeInvoice();
        $unpaid->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now(), 'notes' => 'Catatan faktur gabungan']);

        $partial = $this->makeInvoice();
        $partial->update([
            'status' => InvoiceStatus::PartiallyPaid,
            'issued_at' => now(),
            'paid_amount' => 400000,
            'remaining_amount' => 600000,
        ]);

        $paid = $this->makeInvoice();
        $paid->update([
            'status' => InvoiceStatus::Paid,
            'issued_at' => now(),
            'paid_amount' => 1000000,
            'remaining_amount' => 0,
        ]);

        $this->actingAs($this->admin)->get(route('combined-invoices.index'))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('CombinedInvoices/Index')
                ->has('documents.data', 0)
                ->where('canViewProfit', true)
                ->where('canCreate', true)
        );
        $this->assertDatabaseCount('combined_invoice_documents', 0);

        $this->get(route('combined-invoices.create'))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('CombinedInvoices/Create')
                ->has('customers', 1)
                ->has('customers.0.invoices', 2)
                ->has('couriers', 1)
                ->where('defaultDueDate', now()->addWeek()->toDateString())
        );

        $this->post(route('combined-invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_ids' => [$unpaid->id, $partial->id],
            'use_due_date' => true,
            'due_date' => '2026-07-24',
            'courier_id' => $this->courier->id,
            'shipping_cost' => 50000,
        ])->assertRedirect();
        $document = CombinedInvoiceDocument::firstOrFail();
        $this->assertMatchesRegularExpression('#^FKT/2026/07/\d{5}$#', $document->facture_number);
        $this->assertSame('2026-07-24', $document->due_date->toDateString());
        $this->assertSame($this->courier->id, $document->courier_id);
        $this->assertSame('50000.00', $document->shipping_cost);
        $this->assertDatabaseHas('cash_transactions', [
            'combined_invoice_document_id' => $document->id,
            'type' => 'out',
            'category' => 'Ongkir Driver',
            'amount' => 50000,
            'reference_number' => $document->facture_number,
        ]);
        $this->assertCount(2, $document->invoices);

        $this->get(route('combined-invoices.show', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CombinedInvoices/Show')
                ->has('invoices', 2)
                ->where('document.facture_number', fn ($number) => str_starts_with($number, 'FKT/'))
                ->where('canManagePayments', true)
                ->where('canViewProfit', true)
                ->where('totals.remaining_total', '1600000')
                ->where('document.due_date', '2026-07-24')
                ->where('document.courier_name', $this->courier->name)
                ->where('document.shipping_cost', '50000.00')
                ->where('canEditDueDate', true)
                ->where('canEdit', true)
                ->where('canDelete', true)
                ->where('deletionLocked', false)
                ->missing('invoices.0.notes')
                ->where('totals.gross_profit_total', '500000')
                ->where('totals.profit_base_total', '2000000'));

        $this->get(route('combined-invoices.edit', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CombinedInvoices/Create')
                ->where('document.id', $document->id)
                ->where('document.facture_number', $document->facture_number)
                ->has('document.invoice_ids', 2));

        $this->put(route('combined-invoices.update', $document), [
            'customer_id' => $this->customer->id,
            'invoice_ids' => [$unpaid->id, $partial->id],
            'use_due_date' => true,
            'due_date' => '2026-07-29',
            'courier_id' => $this->courier->id,
            'shipping_cost' => 75000,
        ])->assertRedirect(route('combined-invoices.show', $document));
        $this->assertSame('2026-07-29', $document->fresh()->due_date->toDateString());
        $this->assertDatabaseHas('cash_transactions', [
            'combined_invoice_document_id' => $document->id,
            'amount' => 75000,
        ]);

        $this->put(route('combined-invoices.due-date.update', $document), [
            'use_due_date' => false,
            'due_date' => null,
        ])->assertRedirect();
        $this->assertNull($document->fresh()->due_date);

        $this->put(route('combined-invoices.due-date.update', $document), [
            'use_due_date' => true,
            'due_date' => '2026-07-30',
        ])->assertRedirect();

        $this->get(route('combined-invoices.print', $document))
            ->assertOk()
            ->assertSee($unpaid->invoice_number)
            ->assertSee($partial->invoice_number)
            ->assertDontSee($paid->invoice_number)
            ->assertDontSee('Catatan')
            ->assertDontSee('Catatan faktur gabungan')
            ->assertSee('Syarat pembayaran')
            ->assertSee('Nomor Faktur')
            ->assertSee('30/07/2026')
            ->assertDontSee('<th>Jatuh Tempo</th>', false)
            ->assertDontSee('Margin')
            ->assertSee('window.print()', false)
            ->assertDontSee('<button onclick="window.print()">Cetak</button>', false);

        $this->get(route('combined-invoices.pdf', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $staff = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $staff->assignRole('Staff');
        $this->actingAs($staff)
            ->get(route('combined-invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewProfit', false)
                ->missing('documents.data.0.gross_profit_total'));
        $this->get(route('combined-invoices.show', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewProfit', false)
                ->missing('invoices.0.gross_profit')
                ->missing('totals.gross_profit_total'));

        $deletableInvoice = $this->makeInvoice();
        $deletableInvoice->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now()]);
        $this->actingAs($this->admin)->post(route('combined-invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_ids' => [$deletableInvoice->id],
            'use_due_date' => false,
            'due_date' => null,
        ])->assertRedirect();
        $deletableDocument = CombinedInvoiceDocument::latest('id')->firstOrFail();
        $this->delete(route('combined-invoices.destroy', $deletableDocument))
            ->assertRedirect(route('combined-invoices.index'));
        $this->assertDatabaseMissing('combined_invoice_documents', ['id' => $deletableDocument->id]);
        $this->assertDatabaseHas('invoices', ['id' => $deletableInvoice->id]);
    }

    public function test_combined_invoice_payment_allocates_oldest_invoice_and_closes_facture_when_paid(): void
    {
        $first = $this->makeInvoice();
        $first->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now(), 'due_date' => '2026-07-20']);
        $second = $this->makeInvoice();
        $second->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now(), 'due_date' => '2026-07-25']);

        $this->actingAs($this->admin)->post(route('combined-invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_ids' => [$first->id, $second->id],
            'use_due_date' => false,
            'due_date' => null,
        ])->assertRedirect();
        $document = CombinedInvoiceDocument::where('customer_id', $this->customer->id)->where('status', 'open')->firstOrFail();
        $this->assertMatchesRegularExpression('#^FKT/2026/07/\d{5}$#', $document->facture_number);
        $this->assertNull($document->due_date);

        $this->post(route('combined-invoices.pay', $document), [
            'payment_date' => '2026-07-17',
            'amount' => 2000001,
            'payment_method' => 'transfer',
            'commission_enabled' => false,
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);

        foreach ([10.5, 101] as $invalidPercentage) {
            $this->post(route('combined-invoices.pay', $document), [
                'payment_date' => '2026-07-17',
                'amount' => 100000,
                'payment_method' => 'transfer',
                'commission_enabled' => true,
                'commission_base' => 'margin',
                'commission_type' => 'percentage',
                'commission_value' => $invalidPercentage,
            ])->assertSessionHasErrors('commission_value');
        }
        $this->assertDatabaseCount('payments', 0);

        $this->post(route('combined-invoices.pay', $document), [
            'payment_date' => '2026-07-17',
            'amount' => 1500000,
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'reference_number' => 'FAKTUR-001',
            'notes' => 'Pembayaran pertama',
            'commission_enabled' => true,
            'commission_base' => 'margin',
            'commission_type' => 'percentage',
            'commission_value' => 10,
            'commission_notes' => 'Komisi payment gateway',
        ])->assertRedirect(route('combined-invoices.show', $document));

        $this->assertSame('1000000.00', $first->fresh()->paid_amount);
        $this->assertSame('500000.00', $second->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseHas('payments', [
            'combined_invoice_document_id' => $document->id,
            'notes' => 'Pembayaran Faktur '.$document->facture_number.'. Pembayaran pertama',
        ]);
        $this->assertSame(2, CashTransaction::query()
            ->where('description', 'like', "Pembayaran Faktur {$document->facture_number} | Invoice %")
            ->count());
        $this->assertDatabaseHas('facture_commissions', [
            'combined_invoice_document_id' => $document->id,
            'commission_base' => 'margin',
            'commission_type' => 'percentage',
            'commission_value' => 10,
            'base_amount' => 500000,
            'commission_amount' => 50000,
            'status' => 'unpaid',
        ]);
        $commission = FactureCommission::firstOrFail();
        $this->get(route('facture-commissions.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('FactureCommissions/Index')
            ->where('commissions.data.0.document.facture_number', $document->facture_number)
            ->where('commissions.data.0.status', 'unpaid'));
        $this->get(route('facture-commissions.show', $commission))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('FactureCommissions/Show')
            ->where('commission.commission_amount', '50000.00')
            ->where('invoices.0.items.0.purchase_price', 75000)
            ->where('invoices.0.items.0.selling_price', 100000)
            ->where('invoices.0.items.0.profit', 250000)
            ->where('canPay', true));

        $this->post(route('facture-commissions.pay', $commission), [
            'paid_date' => '2026-07-18',
            'payment_method' => 'qris',
            'payment_notes' => 'Komisi payment gateway',
        ])->assertRedirect();
        $this->assertDatabaseHas('facture_commissions', ['id' => $commission->id, 'status' => 'paid', 'payment_method' => 'qris']);
        $this->assertDatabaseHas('cash_transactions', [
            'type' => 'out',
            'category' => 'Komisi Faktur',
            'amount' => 50000,
            'reference_number' => $document->facture_number,
            'notes' => 'Komisi payment gateway',
        ]);
        $commissionCash = $commission->fresh()->cashTransaction;
        $this->get(route('cash-out.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.id', $commissionCash->id)
            ->where('rows.data.0.facture_commission_exists', true));
        $cashPayload = [
            'transaction_date' => '2026-07-19',
            'category' => 'Komisi Faktur',
            'description' => 'Percobaan edit langsung',
            'payment_method' => 'cash',
            'amount' => 1000,
            'reference_number' => $document->facture_number,
            'notes' => null,
        ];
        $this->put(route('cash-out.update', $commissionCash), $cashPayload)->assertStatus(422);
        $this->delete(route('cash-out.destroy', $commissionCash))->assertStatus(422);

        $this->put(route('facture-commissions.update', $commission), [
            'facture_payment_date' => '2026-07-17',
            'commission_base' => 'margin',
            'commission_type' => 'nominal',
            'commission_value' => 40000,
            'notes' => 'Komisi dikoreksi',
            'paid_date' => '2026-07-19',
            'payment_method' => 'transfer',
            'payment_notes' => 'Pembayaran komisi dikoreksi',
        ])->assertRedirect();
        $this->assertDatabaseHas('facture_commissions', [
            'id' => $commission->id,
            'commission_amount' => 40000,
            'paid_date' => '2026-07-19',
            'payment_method' => 'transfer',
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'id' => $commissionCash->id,
            'amount' => 40000,
            'payment_method' => 'transfer',
            'notes' => 'Pembayaran komisi dikoreksi',
        ]);
        $this->post(route('facture-commissions.pay', $commission), [
            'paid_date' => '2026-07-18',
            'payment_method' => 'cash',
        ])->assertStatus(422);
        $this->assertSame('open', $document->fresh()->status);

        $this->get(route('combined-invoices.show', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEdit', false)
                ->where('canDelete', true)
                ->where('deletionLocked', true)
                ->where('commissionWarningPercentage', 10));
        $this->delete(route('combined-invoices.destroy', $document))->assertStatus(422);
        $this->assertDatabaseHas('combined_invoice_documents', ['id' => $document->id]);

        $this->post(route('combined-invoices.pay', $document), [
            'payment_date' => '2026-07-17',
            'amount' => 500000,
            'payment_method' => 'cash',
            'commission_enabled' => false,
        ])->assertRedirect(route('combined-invoices.show', $document));

        $this->assertSame(InvoiceStatus::Paid, $second->fresh()->status);
        $this->assertSame('closed', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->closed_at);
        $this->assertDatabaseCount('cash_transactions', 4);

        $this->get(route('combined-invoices.show', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEditDueDate', false)
                ->where('totals.commission_total', '40000')
                ->has('payments', 3));
        $this->put(route('combined-invoices.due-date.update', $document), [
            'use_due_date' => true,
            'due_date' => '2026-07-30',
        ])->assertStatus(422);

        $paymentToCorrect = $second->payments()->where('amount', 500000)->firstOrFail();
        $this->put(route('combined-invoices.payments.update', [$document, $paymentToCorrect]), [
            'payment_date' => '2026-07-18',
            'amount' => 400000,
            'payment_method' => 'qris',
            'bank_name' => null,
            'reference_number' => 'KOREKSI-001',
            'notes' => 'Koreksi salah input pembayaran',
        ])->assertRedirect(route('combined-invoices.show', $document));

        $this->assertSame('400000.00', $paymentToCorrect->fresh()->amount);
        $this->assertSame('900000.00', $second->fresh()->paid_amount);
        $this->assertSame('100000.00', $second->fresh()->remaining_amount);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $second->fresh()->status);
        $this->assertSame('open', $document->fresh()->status);
        $this->assertNull($document->fresh()->closed_at);
        $this->assertDatabaseHas('cash_transactions', [
            'payment_id' => $paymentToCorrect->id,
            'transaction_date' => '2026-07-18 00:00:00',
            'payment_method' => 'qris',
            'amount' => 400000,
            'reference_number' => 'KOREKSI-001',
        ]);

        $this->delete(route('facture-commissions.destroy', $commission))
            ->assertRedirect(route('facture-commissions.index'));
        $this->assertDatabaseMissing('facture_commissions', ['id' => $commission->id]);
        $this->assertSoftDeleted('cash_transactions', ['id' => $commissionCash->id]);
    }

    public function test_purchase_price_is_absent_from_issued_invoice_print(): void
    {
        $this->customer->update(['phone' => '081234567890', 'address' => 'Jl. Maju No. 1']);
        $invoice = $this->makeInvoice();
        $invoice->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now()]);
        $response = $this->actingAs($this->admin)->get(route('invoices.print', $invoice))->assertOk();
        $response
            ->assertDontSee('Harga Beli')
            ->assertDontSee('60000.00')
            ->assertDontSee('INFORMASI PEMBAYARAN')
            ->assertSee('Tagihan Kepada :')
            ->assertSee('PT Maju')
            ->assertSee('(Budi)')
            ->assertSee('(081234567890)')
            ->assertSee('Alamat :')
            ->assertSee('Jl. Maju No. 1')
            ->assertSee('Alamat:')
            ->assertSee('No. HP:')
            ->assertDontSee('Email:')
            ->assertDontSee('NPWP:')
            ->assertSee('<span class="invoice-heading-label">INVOICE</span>', false)
            ->assertSee('Tanggal Invoice :')
            ->assertSee('No Invoice :')
            ->assertSee('No PO :')
            ->assertDontSee('| Tanggal :')
            ->assertDontSee('| No Invoice :')
            ->assertDontSee('Tanggal Jatuh Tempo :')
            ->assertDontSee('Status:')
            ->assertDontSee('Catatan')
            ->assertDontSee('Syarat pembayaran')
            ->assertSee('Penerima')
            ->assertSee('Pengantar (Kurir)')
            ->assertSee('Bambang Kurir')
            ->assertSee('Di Buat Oleh :')
            ->assertSee($this->admin->name)
            ->assertSee($invoice->created_at->format('d/m/Y'))
            ->assertDontSee('QR verifikasi invoice')
            ->assertSee('window.print()', false)
            ->assertDontSee('Cetak Invoice')
            ->assertSee('@page { size: A5 portrait;', false);
    }

    public function test_continuous_form_print_uses_full_9_5_by_11_inch_paper_and_readable_font(): void
    {
        CompanySetting::query()->updateOrCreate([], [
            'company_name' => 'PT Maju',
            'invoice_prefix' => 'INV',
            'printer_type' => 'dot_matrix',
            'printer_paper_size' => 'continuous_9_5x11',
            'printer_orientation' => 'portrait',
        ]);

        $invoice = $this->makeInvoice();
        $invoice->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now()]);

        $this->actingAs($this->admin)
            ->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('@page { size: 241.3mm 279.4mm;', false)
            ->assertSee('margin: 15mm 16mm 10mm;', false)
            ->assertSee('body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; line-height: 1.4;', false)
            ->assertSee('.print-sheet { width: 100%; max-width: 100%; overflow: visible; }', false)
            ->assertSee('.print-sheet { padding-top: 5mm; }', false)
            ->assertSee('.details { margin: 10mm 0 9px; }', false)
            ->assertSee('.items-table td { height: auto; padding: 4.5px 5px; font-size: 10pt; line-height: 1.35;', false)
            ->assertSee('.items-table thead th { text-align: center; }', false)
            ->assertSee('border: 1px solid #000;', false)
            ->assertSee('<span class="invoice-heading-label">INVOICE</span>', false)
            ->assertSee('<span class="invoice-meta-label">Tanggal Invoice :</span>', false)
            ->assertSee('<span class="invoice-meta-label">No Invoice :</span>', false)
            ->assertSee('<span class="invoice-meta-label">No PO :</span>', false)
            ->assertDontSee('| No PO :', false)
            ->assertDontSee('background: #0369a1;', false);
    }

    public function test_continuous_form_facture_print_is_monochrome_and_uses_bordered_table(): void
    {
        CompanySetting::query()->updateOrCreate([], [
            'company_name' => 'PT Maju',
            'invoice_prefix' => 'INV',
            'printer_type' => 'dot_matrix',
            'printer_paper_size' => 'continuous_9_5x11',
            'printer_orientation' => 'portrait',
        ]);

        $invoice = $this->makeInvoice();
        $invoice->update(['status' => InvoiceStatus::Unpaid, 'issued_at' => now()]);
        $this->actingAs($this->admin)->post(route('combined-invoices.store'), [
            'customer_id' => $invoice->customer_id,
            'invoice_ids' => [$invoice->id],
            'use_due_date' => false,
            'due_date' => null,
        ])->assertRedirect();

        $document = CombinedInvoiceDocument::latest('id')->firstOrFail();

        $this->get(route('combined-invoices.print', $document))
            ->assertOk()
            ->assertSee('@page { size: 241.3mm 279.4mm; margin: 15mm 16mm 10mm;', false)
            ->assertSee('body { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.35;', false)
            ->assertSee('class="invoice-table"', false)
            ->assertSee('.print-sheet { width: 100%; max-width: 100%; overflow: visible; }', false)
            ->assertSee('.print-sheet { padding-top: 5mm; }', false)
            ->assertSee('.title { margin: 4mm 0 7px;', false)
            ->assertSee('.invoice-table td { height: auto; padding: 3.5px 4px; font-size: 9pt; line-height: 1.25;', false)
            ->assertSee('border: 1px solid #000;', false)
            ->assertDontSee('Email:')
            ->assertDontSee('NPWP:')
            ->assertDontSee('background:#0369a1', false);
    }

    public function test_overdue_invoice_status_can_be_applied_without_hiding_financial_record(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->update(['status' => InvoiceStatus::Overdue, 'due_date' => today()->subDay()]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'overdue']);
    }

    public function test_database_cleanup_tab_is_super_admin_only_and_requires_password_confirmation(): void
    {
        $this->admin->update(['password' => 'rahasia-admin']);
        $this->actingAs($this->admin)->get(route('company.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canDeleteData', true)
                ->has('cleanupCounts.customers')
                ->has('cleanupCounts.invoices')
                ->has('cleanupCounts.factures')
                ->has('cleanupCounts.shipping')
                ->has('cleanupCounts.cash_in')
                ->has('cleanupCounts.cash_out'));

        $cashIn = app(CashTransactionService::class)->create('in', [
            'transaction_date' => '2026-07-19', 'category' => 'Manual', 'description' => 'Tetap ada',
            'payment_method' => 'cash', 'amount' => 100000,
        ], $this->admin->id);
        $cashOut = app(CashTransactionService::class)->create('out', [
            'transaction_date' => '2026-07-19', 'category' => 'Operasional', 'description' => 'Dihapus',
            'payment_method' => 'cash', 'amount' => 25000,
        ], $this->admin->id);

        $this->delete(route('company.data.purge'), [
            'scope' => 'cash_out', 'password' => 'salah', 'confirmation' => 'HAPUS DATA',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseHas('cash_transactions', ['id' => $cashOut->id]);

        $this->delete(route('company.data.purge'), [
            'scope' => 'cash_out', 'password' => 'rahasia-admin', 'confirmation' => 'HAPUS DATA',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('cash_transactions', ['id' => $cashOut->id]);
        $this->assertDatabaseHas('cash_transactions', ['id' => $cashIn->id]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'database_cleanup', 'action' => 'purge']);

        $staff = User::factory()->create(['password' => 'rahasia-staff', 'email_verified_at' => now(), 'is_active' => true]);
        $staff->assignRole('Admin');
        $this->actingAs($staff)->delete(route('company.data.purge'), [
            'scope' => 'cash_out', 'password' => 'rahasia-staff', 'confirmation' => 'HAPUS DATA',
        ])->assertForbidden();
    }

    public function test_deleting_cash_in_removes_payments_and_restores_invoice_balance(): void
    {
        $this->admin->update(['password' => 'rahasia-admin']);
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false, $this->courier->id, 0);
        app(PaymentService::class)->record($invoice->fresh(), [
            'payment_date' => '2026-07-19', 'amount' => 250000, 'payment_method' => 'cash',
        ], $this->admin->id);

        $this->actingAs($this->admin)->delete(route('company.data.purge'), [
            'scope' => 'cash_in', 'password' => 'rahasia-admin', 'confirmation' => 'HAPUS DATA',
        ])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseMissing('cash_transactions', ['type' => 'in']);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame($invoice->grand_total, $invoice->remaining_amount);
    }

    public function test_database_cleanup_failure_returns_to_settings_with_a_safe_error_message(): void
    {
        $this->admin->update(['password' => 'rahasia-admin']);
        $cleanup = \Mockery::mock(DatabaseCleanupService::class);
        $cleanup->shouldReceive('purge')->once()->with('invoices')->andThrow(new \RuntimeException('database failure'));
        $this->app->instance(DatabaseCleanupService::class, $cleanup);

        $this->actingAs($this->admin)->from(route('company.edit'))->delete(route('company.data.purge'), [
            'scope' => 'invoices', 'password' => 'rahasia-admin', 'confirmation' => 'HAPUS DATA',
        ])->assertRedirect(route('company.edit'))->assertSessionHasErrors('cleanup');

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
    }

    public function test_database_cleanup_cannot_delete_clients_until_all_invoices_are_removed(): void
    {
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false, $this->courier->id, 15000);
        app(PaymentService::class)->record($invoice->fresh(), [
            'payment_date' => '2026-07-19', 'amount' => 100000, 'payment_method' => 'cash',
        ], $this->admin->id);
        $archivedCustomer = Customer::factory()->create();
        $archivedCustomer->delete();

        try {
            app(DatabaseCleanupService::class)->purge('customers');
            $this->fail('Penghapusan client seharusnya ditolak selama invoice masih ada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scope', $exception->errors());
        }

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
        app(DatabaseCleanupService::class)->purge('invoices');
        $result = app(DatabaseCleanupService::class)->purge('customers');

        $this->assertSame(2, $result['before']['customers']);
        $this->assertSame(0, $result['after']['customers']);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('courier_deliveries', 0);
        $this->assertDatabaseMissing('cash_transactions', ['invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('products', ['id' => $this->product->id]);
    }

    public function test_all_database_cleanup_scopes_keep_relations_consistent(): void
    {
        $invoice = $this->makeInvoice();
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false, $this->courier->id, 15000);
        app(PaymentService::class)->record($invoice->fresh(), [
            'payment_date' => '2026-07-19', 'amount' => 100000, 'payment_method' => 'cash',
        ], $this->admin->id);
        $document = app(CombinedInvoiceService::class)->create(
            $this->customer,
            [$invoice->id],
            null,
            $this->courier->id,
            10000,
            $this->admin->id,
        );

        $cleanup = app(DatabaseCleanupService::class);
        $cleanup->purge('factures');
        $this->assertDatabaseMissing('combined_invoice_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'combined_invoice_document_id' => null]);

        $cleanup->purge('shipping');
        $this->assertDatabaseCount('courier_deliveries', 0);
        $this->assertDatabaseCount('courier_shipping_deposits', 0);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'courier_id' => null, 'shipping_cost' => 0]);

        $cleanup->purge('cash_in');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'paid_amount' => 0, 'status' => 'unpaid']);

        app(CashTransactionService::class)->create('out', [
            'transaction_date' => '2026-07-19', 'category' => 'Operasional', 'description' => 'Biaya',
            'payment_method' => 'cash', 'amount' => 10000,
        ], $this->admin->id);
        $cleanup->purge('cash_out');
        $this->assertDatabaseMissing('cash_transactions', ['type' => 'out']);

        $cleanup->purge('invoices');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
        $this->assertDatabaseHas('products', ['id' => $this->product->id]);
    }

    private function makeInvoice(): Invoice
    {
        return app(InvoiceService::class)->create(['customer_id' => $this->customer->id, 'courier_id' => $this->courier->id, 'invoice_date' => '2026-07-15', 'due_date' => '2026-07-30', 'discount_type' => 'nominal', 'discount_value' => 0, 'tax_percentage' => 0, 'shipping_cost' => 0, 'items' => [['product_id' => $this->product->id, 'purchase_price' => 75000, 'selling_price' => 100000, 'quantity' => 10, 'volume' => 9, 'calculation_method' => 'qty_volume']]], $this->admin->id);
    }
}
