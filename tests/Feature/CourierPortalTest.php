<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\CourierDelivery;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourierPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $courierUser;

    private Courier $courier;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->admin->assignRole('Super Admin');
        $this->courierUser = User::factory()->create(['name' => 'Budi Kurir', 'email_verified_at' => now(), 'is_active' => true]);
        $this->courierUser->assignRole('Kurir');
        $this->courier = Courier::create(['user_id' => $this->courierUser->id, 'courier_code' => 'KUR-01', 'name' => 'Budi Kurir', 'phone' => '0812', 'vehicle_type' => 'Motor', 'license_plate' => 'B 1 KUR', 'is_active' => true]);
        $this->customer = Customer::create(['customer_code' => 'CUS-01', 'name' => 'Sinta', 'company_name' => 'PT Tujuan', 'address' => 'Jakarta', 'is_active' => true]);
        $category = ProductCategory::create(['name' => 'Umum', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'sku' => 'BRG-01', 'name' => 'Barang', 'unit' => 'Pcs', 'purchase_price' => 10000, 'average_purchase_price' => 10000, 'selling_price' => 20000, 'stock' => 0, 'minimum_stock' => 0, 'is_active' => true]);
    }

    public function test_courier_role_is_restricted_to_its_portal_and_dashboard_redirects(): void
    {
        $this->actingAs($this->courierUser)->get(route('dashboard'))->assertRedirect(route('courier.tasks.index'));
        $this->actingAs($this->courierUser)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($this->courierUser)->get(route('couriers.index'))->assertForbidden();
        $this->actingAs($this->courierUser)->get(route('courier.tasks.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('CourierPortal/Tasks'));
    }

    public function test_courier_bank_account_can_be_updated_by_admin_and_from_courier_profile(): void
    {
        $this->actingAs($this->admin)->put(route('couriers.update', $this->courier), [
            'courier_code' => 'KUR-01',
            'name' => 'Budi Kurir',
            'phone' => '0812',
            'vehicle_type' => 'Motor',
            'license_plate' => 'B 1 KUR',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Budi Kurir',
            'notes' => null,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('couriers', [
            'id' => $this->courier->id,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Budi Kurir',
        ]);

        $this->actingAs($this->admin)->get(route('couriers.show', $this->courier))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Couriers/Show')
            ->where('courier.bank_name', 'BCA')
            ->where('courier.bank_account_number', '1234567890')
            ->where('courier.bank_account_name', 'Budi Kurir'));

        $this->actingAs($this->courierUser)->patch(route('courier.profile.update'), [
            'name' => 'Budi Kurir',
            'username' => $this->courierUser->username,
            'email' => $this->courierUser->email,
            'phone' => '0812',
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '9876543210',
            'bank_account_name' => 'Budi Santoso',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->courierUser)->get(route('courier.profile.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('CourierPortal/Profile')
            ->where('courier.bank_name', 'Bank Mandiri')
            ->where('courier.bank_account_number', '9876543210')
            ->where('courier.bank_account_name', 'Budi Santoso'));
    }

    public function test_issued_invoice_becomes_task_and_courier_can_complete_with_photo_time_and_gps(): void
    {
        Storage::fake('public');
        $invoice = $this->issuedInvoice();
        $delivery = CourierDelivery::where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($this->courierUser)->post(route('courier.location.store'), ['latitude' => -6.2, 'longitude' => 106.8166, 'accuracy' => 8])->assertOk();
        $this->actingAs($this->courierUser)->post(route('courier.tasks.accept', $delivery), ['latitude' => -6.2, 'longitude' => 106.8166])->assertRedirect();
        $this->assertSame(CourierDelivery::ACCEPTED, $delivery->fresh()->status);
        $this->actingAs($this->courierUser)->post(route('courier.tasks.start', $delivery), [
            'latitude' => -6.205,
            'longitude' => 106.818,
            'accuracy' => 6,
            'departure_address' => 'Gudang Jakarta, Indonesia',
            'departure_photo' => UploadedFile::fake()->image('berangkat.jpg', 800, 1200),
        ])->assertRedirect();
        $delivery = $delivery->fresh();
        $this->assertSame(CourierDelivery::IN_TRANSIT, $delivery->status);
        $this->assertNotNull($delivery->departure_photo_taken_at);
        $this->assertSame('Gudang Jakarta, Indonesia', $delivery->departure_address);
        Storage::disk('public')->assertExists($delivery->departure_photo_path);

        $this->actingAs($this->courierUser)->post(route('courier.tasks.complete', $delivery), [
            'latitude' => -6.21,
            'longitude' => 106.82,
            'accuracy' => 5,
            'delivery_address' => 'Jl. Sudirman No. 1, Jakarta, Indonesia',
            'delivery_notes' => 'Diterima pelanggan',
            'proof_photo' => UploadedFile::fake()->image('bukti.jpg', 800, 600),
        ])->assertRedirect();

        $delivery = $delivery->fresh();
        $this->assertSame(CourierDelivery::DELIVERED, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($delivery->proof_taken_at);
        $this->assertSame('-6.2100000', $delivery->delivered_latitude);
        $this->assertSame('Jl. Sudirman No. 1, Jakarta, Indonesia', $delivery->delivery_address);
        Storage::disk('public')->assertExists($delivery->proof_photo_path);
        $this->assertDatabaseHas('courier_locations', ['courier_id' => $this->courier->id]);

        $this->actingAs($this->admin)->get(route('invoices.show', $invoice))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('invoice.delivery.status', CourierDelivery::DELIVERED)
                ->where('invoice.delivery.accepted_at', fn ($value) => filled($value))
                ->where('invoice.delivery.departed_at', fn ($value) => filled($value))
                ->where('invoice.delivery.departure_address', 'Gudang Jakarta, Indonesia')
                ->where('invoice.delivery.departure_proof_url', fn ($value) => filled($value))
                ->where('invoice.delivery.delivered_at', fn ($value) => filled($value))
                ->where('invoice.delivery.delivery_address', 'Jl. Sudirman No. 1, Jakarta, Indonesia')
                ->where('invoice.delivery.proof_url', fn ($value) => filled($value))
        );
    }

    public function test_courier_sees_paid_and_unpaid_shipping_and_cannot_take_another_couriers_task(): void
    {
        $invoice = $this->issuedInvoice();
        $delivery = CourierDelivery::where('invoice_id', $invoice->id)->firstOrFail();

        $otherUser = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $otherUser->assignRole('Kurir');
        Courier::create(['user_id' => $otherUser->id, 'courier_code' => 'KUR-02', 'name' => 'Kurir Lain', 'is_active' => true]);
        $this->actingAs($otherUser)->post(route('courier.tasks.accept', $delivery))->assertNotFound();

        $this->actingAs($this->courierUser)->get(route('courier.earnings.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('CourierPortal/Earnings')
            ->where('summary.unpaid', 25000)
            ->where('summary.paid', 0)
            ->has('rows.data', 1));
    }

    public function test_admin_can_open_map_and_creating_courier_user_creates_courier_profile(): void
    {
        $this->courier->update(['is_online' => true, 'last_latitude' => -6.2, 'last_longitude' => 106.8166, 'last_location_at' => now()->subHour()]);
        $this->actingAs($this->admin)->get(route('couriers.map'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Couriers/Map')
            ->has('couriers', 1)
            ->has('couriers.0.invoice_numbers', 0)
            ->where('summary.online', 1));

        $firstInvoice = $this->issuedInvoice();
        $secondInvoice = $this->issuedInvoice();
        CourierDelivery::whereIn('invoice_id', [$firstInvoice->id, $secondInvoice->id])->update([
            'status' => CourierDelivery::ACCEPTED,
            'accepted_at' => now(),
        ]);
        $this->actingAs($this->admin)->get(route('couriers.map'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('couriers.0.status', 'delivering')
            ->has('couriers.0.invoice_numbers', 2)
            ->where('couriers.0.invoice_numbers.0', $firstInvoice->invoice_number)
            ->where('couriers.0.invoice_numbers.1', $secondInvoice->invoice_number)
            ->where('summary.online', 0)
            ->where('summary.delivering', 1));

        $this->courier->update(['is_online' => false]);
        $this->actingAs($this->admin)->get(route('couriers.map'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('couriers', 0)
            ->where('summary.online', 0)
            ->where('summary.offline', 1));
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Users')
            ->where('roles', fn ($roles) => collect($roles)->contains('Kurir'))
            ->missing('couriers'));
        $this->actingAs($this->admin)->get(route('couriers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Masters/Index')
            ->where('type', 'courier')
            ->where('canCreateCourierUser', true));

        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Rina Pengantar',
            'username' => 'rina_kurir',
            'email' => 'rina.kurir@example.test',
            'password' => 'bangbens1',
            'role' => 'Kurir',
            'is_active' => true,
        ])->assertRedirect();

        $user = User::where('username', 'rina_kurir')->firstOrFail();
        $this->assertTrue($user->hasRole('Kurir'));
        $this->assertDatabaseHas('couriers', [
            'user_id' => $user->id,
            'courier_code' => 'KUR-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            'name' => 'Rina Pengantar',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => 'Rina Kurir Baru',
            'username' => 'rina_kurir',
            'email' => 'rina.kurir@example.test',
            'password' => '',
            'role' => 'Staff',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('couriers', [
            'user_id' => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_user_validation_is_readable_and_all_non_courier_roles_can_be_saved(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Kurir Password Pendek',
            'username' => 'kurir_pendek',
            'email' => 'kurir.pendek@example.test',
            'password' => '123456',
            'role' => 'Kurir',
            'is_active' => true,
        ])->assertSessionHasErrors([
            'password' => 'Password minimal 8 karakter.',
        ]);

        $this->assertDatabaseMissing('users', ['username' => 'kurir_pendek']);
        $this->assertDatabaseCount('couriers', 1);

        foreach (['Admin', 'Finance', 'Staff', 'Pimpinan'] as $index => $role) {
            $username = 'pengguna_'.strtolower($role);

            $this->actingAs($this->admin)->post(route('users.store'), [
                'name' => 'Pengguna '.$role,
                'username' => $username,
                'email' => $username.'@example.test',
                'password' => 'bangbens1',
                'role' => $role,
                'is_active' => $index % 2 === 0,
            ])->assertSessionHasNoErrors();

            $user = User::where('username', $username)->firstOrFail();
            $this->assertTrue($user->hasRole($role));
            $this->assertDatabaseMissing('couriers', ['user_id' => $user->id]);
        }
    }

    private function issuedInvoice()
    {
        $invoice = app(InvoiceService::class)->create([
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'invoice_date' => '2026-07-17',
            'due_date' => '2026-07-24',
            'discount_type' => 'nominal',
            'discount_value' => 0,
            'tax_percentage' => 0,
            'shipping_cost' => 25000,
            'items' => [[
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'unit' => 'Pcs',
                'purchase_price' => 10000,
                'selling_price' => 20000,
                'quantity' => 2,
            ]],
        ], $this->admin->id);
        app(InvoiceService::class)->issue($invoice, $this->admin->id, false);

        return $invoice->fresh();
    }
}
