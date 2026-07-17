<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Courier;
use App\Models\CourierDelivery;
use App\Models\CourierLocation;
use App\Models\Invoice;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CourierPortalController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function tasks(Request $request)
    {
        $courier = $this->courier($request);
        $this->syncTasks($courier);

        $base = CourierDelivery::query()
            ->where('courier_id', $courier->id)
            ->with(['invoice.customer:id,name,company_name,address,phone', 'invoice.shippingDeposit']);

        return Inertia::render('CourierPortal/Tasks', [
            'courier' => $courier,
            'availableTasks' => (clone $base)->where('status', CourierDelivery::PENDING)->latest()->get(),
            'activeTasks' => (clone $base)->whereIn('status', [CourierDelivery::ACCEPTED, CourierDelivery::IN_TRANSIT])->oldest('accepted_at')->get(),
            'completedTasks' => (clone $base)->where('status', CourierDelivery::DELIVERED)->latest('delivered_at')->limit(10)->get(),
            'shippingSummary' => $this->shippingSummary($courier),
        ]);
    }

    public function earnings(Request $request)
    {
        $courier = $this->courier($request);
        $status = $request->string('status')->toString();
        $rows = $courier->shippingDeposits()
            ->with('invoice:id,invoice_number,invoice_date,billing_name,billing_company')
            ->when($status === 'paid', fn ($query) => $query->whereNotNull('paid_at'))
            ->when($status === 'unpaid', fn ($query) => $query->whereNull('paid_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('CourierPortal/Earnings', [
            'courier' => $courier,
            'rows' => $rows,
            'summary' => $this->shippingSummary($courier),
            'filters' => ['status' => $status],
        ]);
    }

    public function profile(Request $request)
    {
        return Inertia::render('CourierPortal/Profile', ['courier' => $this->courier($request)]);
    }

    public function updateProfile(Request $request)
    {
        $courier = $this->courier($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'lowercase', 'alpha_dash', 'max:100', Rule::unique('users')->ignore($request->user())],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($request->user())],
            'phone' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
        ]);

        DB::transaction(function () use ($request, $courier, $data) {
            $request->user()->update(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email']]);
            $courier->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_account_name' => $data['bank_account_name'] ?? null,
            ]);
        });

        return back()->with('success', 'Profil kurir berhasil diperbarui.');
    }

    public function accept(Request $request, CourierDelivery $delivery)
    {
        $courier = $this->courier($request);
        $this->guardDelivery($delivery, $courier, [CourierDelivery::PENDING]);
        $location = $this->validateLocation($request, false);
        $delivery->update(array_merge($location, ['status' => CourierDelivery::ACCEPTED, 'accepted_at' => now()]));
        $this->audit->record('accept', 'courier_delivery', $delivery);

        return back()->with('success', 'Tugas berhasil diambil. Silakan mulai perjalanan saat barang siap diantar.');
    }

    public function start(Request $request, CourierDelivery $delivery)
    {
        $courier = $this->courier($request);
        $this->guardDelivery($delivery, $courier, [CourierDelivery::ACCEPTED]);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'departure_address' => ['required', 'string', 'max:2000'],
            'departure_photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ]);
        $path = $request->file('departure_photo')->store("courier-proofs/{$courier->id}", 'public');

        $delivery->update([
            'status' => CourierDelivery::IN_TRANSIT,
            'departed_at' => now(),
            'departed_latitude' => $data['latitude'],
            'departed_longitude' => $data['longitude'],
            'departed_accuracy' => $data['accuracy'] ?? null,
            'departure_address' => $data['departure_address'],
            'departure_photo_path' => $path,
            'departure_photo_taken_at' => now(),
        ]);
        $this->saveLocation($courier, $data);
        $this->audit->record('start', 'courier_delivery', $delivery);

        return back()->with('success', 'Status diperbarui: Dalam Perjalanan.');
    }

    public function complete(Request $request, CourierDelivery $delivery)
    {
        $courier = $this->courier($request);
        $this->guardDelivery($delivery, $courier, [CourierDelivery::IN_TRANSIT]);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'delivery_address' => ['required', 'string', 'max:2000'],
            'proof_photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $path = $request->file('proof_photo')->store("courier-proofs/{$courier->id}", 'public');

        $delivery->update([
            'status' => CourierDelivery::DELIVERED,
            'delivered_at' => now(),
            'delivered_latitude' => $data['latitude'],
            'delivered_longitude' => $data['longitude'],
            'delivered_accuracy' => $data['accuracy'] ?? null,
            'delivery_address' => $data['delivery_address'],
            'proof_photo_path' => $path,
            'proof_taken_at' => now(),
            'delivery_notes' => $data['delivery_notes'] ?? null,
        ]);
        $this->saveLocation($courier, $data);
        $this->audit->record('complete', 'courier_delivery', $delivery);

        return back()->with('success', 'Pengiriman selesai. Foto, waktu, dan lokasi telah disimpan.');
    }

    public function location(Request $request)
    {
        $courier = $this->courier($request);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        $this->saveLocation($courier, $data);

        return response()->json(['saved' => true, 'recorded_at' => now()->toIso8601String()]);
    }

    public function address(Request $request)
    {
        $this->courier($request);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $cacheKey = sprintf('courier-address:%0.4f:%0.4f', $data['latitude'], $data['longitude']);
        $address = Cache::remember($cacheKey, now()->addDay(), function () use ($data) {
            $response = Http::acceptJson()
                ->withHeaders(['User-Agent' => config('app.name').' Courier/1.0 ('.config('app.url').')'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $data['latitude'],
                    'lon' => $data['longitude'],
                    'zoom' => 18,
                    'addressdetails' => 1,
                    'accept-language' => 'id',
                ]);

            abort_unless($response->successful() && filled($response->json('display_name')), 503, 'Alamat GPS belum dapat ditemukan.');

            return $response->json('display_name');
        });

        return response()->json(['address' => $address]);
    }

    private function courier(Request $request): Courier
    {
        abort_unless($request->user()->hasRole('Kurir') && $request->user()->can('courier.portal'), 403);

        return $request->user()->courier()->where('is_active', true)->firstOrFail();
    }

    private function syncTasks(Courier $courier): void
    {
        Invoice::query()
            ->where('courier_id', $courier->id)
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->pluck('id')
            ->each(fn ($invoiceId) => CourierDelivery::firstOrCreate(
                ['invoice_id' => $invoiceId],
                ['courier_id' => $courier->id, 'status' => CourierDelivery::PENDING],
            ));
    }

    private function guardDelivery(CourierDelivery $delivery, Courier $courier, array $statuses): void
    {
        abort_unless($delivery->courier_id === $courier->id, 404);
        abort_unless(in_array($delivery->status, $statuses, true), 422, 'Status tugas tidak sesuai untuk tindakan ini.');
    }

    private function validateLocation(Request $request, bool $required): array
    {
        $data = $request->validate([
            'latitude' => [$required ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [$required ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
        ]);

        return filled($data['latitude'] ?? null) ? ['accepted_latitude' => $data['latitude'], 'accepted_longitude' => $data['longitude']] : [];
    }

    private function saveLocation(Courier $courier, array $data): void
    {
        $courier->update([
            'last_latitude' => $data['latitude'],
            'last_longitude' => $data['longitude'],
            'last_location_accuracy' => $data['accuracy'] ?? null,
            'last_location_at' => now(),
        ]);
        CourierLocation::create([
            'courier_id' => $courier->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'recorded_at' => now(),
        ]);
    }

    private function shippingSummary(Courier $courier): array
    {
        return [
            'paid' => (float) $courier->shippingDeposits()->whereNotNull('paid_at')->sum('amount'),
            'unpaid' => (float) $courier->shippingDeposits()->whereNull('paid_at')->sum('amount'),
        ];
    }
}
