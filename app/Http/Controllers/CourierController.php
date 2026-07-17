<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourierRequest;
use App\Models\Courier;
use App\Models\CourierDelivery;
use App\Models\CourierShippingDeposit;
use App\Services\AuditLogService;
use App\Services\CashTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CourierController extends Controller
{
    public function __construct(private AuditLogService $audit, private CashTransactionService $cash) {}

    public function index(Request $request)
    {
        $this->authorize('couriers.view');

        $rows = Courier::query()
            ->when($request->search, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%$search%")
                ->orWhere('courier_code', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%")
                ->orWhere('license_plate', 'like', "%$search%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Masters/Index', [
            'title' => 'Data Kurir',
            'type' => 'courier',
            'rows' => $rows,
            'canCreateCourierUser' => $request->user()->can('users.manage'),
        ]);
    }

    public function store(CourierRequest $request)
    {
        $courier = Courier::create($request->validated());
        $this->audit->record('create', 'courier', $courier, null, $courier->toArray());

        return back()->with('success', 'Kurir berhasil disimpan.');
    }

    public function show(Request $request, Courier $courier)
    {
        $this->authorize('couriers.view');

        $deposits = $courier->shippingDeposits()
            ->with('invoice:id,invoice_number,invoice_date,billing_name,billing_company')
            ->with('invoice.delivery:id,invoice_id,status')
            ->latest()
            ->paginate(15);

        return Inertia::render('Couriers/Show', [
            'courier' => $courier,
            'deposits' => $deposits,
            'unpaidTotal' => $courier->shippingDeposits()->whereNull('paid_at')->sum('amount'),
            'paidTotal' => $courier->shippingDeposits()->whereNotNull('paid_at')->sum('amount'),
            'deliveries' => $courier->deliveries()->with('invoice:id,invoice_number,billing_name,billing_company')->latest()->limit(10)->get()->map(function ($delivery) {
                $delivery->proof_url = $delivery->proof_photo_path ? Storage::disk('public')->url($delivery->proof_photo_path) : null;

                return $delivery;
            }),
            'canManageCouriers' => $request->user()->can('couriers.manage'),
        ]);
    }

    public function map(Request $request)
    {
        $this->authorize('couriers.map');

        $couriers = Courier::query()
            ->where('is_active', true)
            ->where('is_online', true)
            ->with(['user:id,name', 'deliveries' => fn ($query) => $query
                ->whereIn('status', [CourierDelivery::ACCEPTED, CourierDelivery::IN_TRANSIT])
                ->orderBy('accepted_at')
                ->orderBy('id')
                ->with('invoice:id,invoice_number')])
            ->orderBy('name')
            ->get()
            ->map(function (Courier $courier) {
                $invoiceNumbers = $courier->deliveries
                    ->pluck('invoice.invoice_number')
                    ->filter()
                    ->values();

                return [
                    'id' => $courier->id,
                    'name' => $courier->name,
                    'courier_code' => $courier->courier_code,
                    'phone' => $courier->phone,
                    'vehicle_type' => $courier->vehicle_type,
                    'license_plate' => $courier->license_plate,
                    'latitude' => $courier->last_latitude,
                    'longitude' => $courier->last_longitude,
                    'accuracy' => $courier->last_location_accuracy,
                    'last_location_at' => $courier->last_location_at,
                    'status' => $invoiceNumbers->isNotEmpty() ? 'delivering' : 'online',
                    'invoice_numbers' => $invoiceNumbers,
                ];
            });

        return Inertia::render('Couriers/Map', [
            'couriers' => $couriers,
            'summary' => [
                'online' => $couriers->where('status', 'online')->count(),
                'delivering' => $couriers->where('status', 'delivering')->count(),
                'offline' => Courier::query()->where('is_active', true)->where('is_online', false)->count(),
            ],
            'refreshedAt' => now()->toIso8601String(),
        ]);
    }

    public function payShippingDeposit(Request $request, Courier $courier, CourierShippingDeposit $deposit)
    {
        $this->authorize('couriers.manage');
        abort_unless($deposit->courier_id === $courier->id, 404);

        $this->cash->payCourierShippingDeposit($deposit, $request->user()->id);

        return back()->with('success', 'Ongkir kurir berhasil dibayarkan dan dicatat ke Kas Keluar.');
    }

    public function update(CourierRequest $request, Courier $courier)
    {
        $old = $courier->toArray();
        $courier->update($request->validated());
        $this->audit->record('update', 'courier', $courier, $old, $courier->fresh()->toArray());

        return back()->with('success', 'Kurir berhasil diperbarui.');
    }

    public function destroy(Courier $courier)
    {
        $this->authorize('couriers.manage');
        $old = $courier->toArray();
        $courier->delete();
        $this->audit->record('delete', 'courier', $courier, $old);

        return back()->with('success', 'Kurir berhasil dinonaktifkan.');
    }
}
