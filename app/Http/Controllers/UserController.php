<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Courier;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const DEFAULT_PASSWORD = '12345678';

    public function __construct(private AuditLogService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('users.manage');
        $rows = User::query()->with(['roles:id,name', 'courier:id,user_id'])->when($request->search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))->latest()->paginate(15)->withQueryString();

        return Inertia::render('Settings/Users', [
            'rows' => $rows,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);
        $data['password'] = self::DEFAULT_PASSWORD;
        $user = DB::transaction(function () use ($data, $role) {
            $user = User::create($data);
            $user->assignRole($role);
            $this->syncCourierProfile($user, $role);

            return $user;
        });
        $this->audit->record('create', 'user', $user, null, $user->toArray());

        return back()->with('success', 'Pengguna berhasil dibuat.');
    }

    public function update(UserRequest $request, User $user)
    {
        abort_if($user->hasRole('Super Admin') && ! $request->user()->hasRole('Super Admin'), 403);
        $data = $request->validated();
        $old = $user->load('roles')->toArray();
        $role = $data['role'];
        unset($data['role']);
        // Status akun hanya boleh diubah melalui aksi khusus agar seluruh
        // pengamanan akun aktif dan Super Admin selalu dijalankan.
        $data['is_active'] = $user->is_active;
        DB::transaction(function () use ($user, $data, $role) {
            $user->update($data);
            $user->syncRoles([$role]);
            $this->syncCourierProfile($user, $role);
        });
        $this->audit->record('update', 'user', $user, $old, $user->fresh('roles')->toArray());

        return back()->with('success', 'Pengguna diperbarui.');
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->authorize('users.manage');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $active = (bool) $data['is_active'];

        if (! $active && $request->user()->is($user)) {
            throw ValidationException::withMessages(['action' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.']);
        }
        $this->ensureSuperAdminRemainsActive($user, $active);

        $old = ['is_active' => $user->is_active];
        DB::transaction(function () use ($user, $active): void {
            $user->update(['is_active' => $active]);
            $user->courier?->update([
                'is_active' => $active,
                ...($active ? [] : ['is_online' => false]),
            ]);
        });
        $this->audit->record('update_status', 'user', $user, $old, ['is_active' => $active]);

        return back()->with('success', $active ? 'Pengguna diaktifkan.' : 'Pengguna dinonaktifkan.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('users.manage');
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['action' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }
        if ($user->hasRole('Super Admin')) {
            throw ValidationException::withMessages(['action' => 'Akun Super Admin tidak dapat dihapus. Nonaktifkan akun lain bila sudah tidak digunakan.']);
        }

        $usage = $this->transactionUsage($user);
        if ($usage !== []) {
            throw ValidationException::withMessages([
                'action' => 'Pengguna tidak dapat dihapus karena memiliki histori '.implode(', ', $usage).'. Gunakan tombol Nonaktifkan agar histori tetap aman.',
            ]);
        }

        $old = $user->load('roles')->toArray();
        DB::transaction(function () use ($user): void {
            $user->courier?->update(['user_id' => null, 'is_active' => false, 'is_online' => false]);
            $user->delete();
        });
        $this->audit->record('delete', 'user', null, $old, null);

        return back()->with('success', 'Pengguna dihapus.');
    }

    private function syncCourierProfile(User $user, string $role): void
    {
        $courier = Courier::withTrashed()->where('user_id', $user->id)->first();

        if ($role !== 'Kurir') {
            $courier?->update(['is_active' => false]);

            return;
        }

        if (! $courier) {
            $courier = Courier::create([
                'user_id' => $user->id,
                'courier_code' => $this->courierCodeFor($user),
                'name' => $user->name,
                'is_active' => $user->is_active,
            ]);
        } else {
            if ($courier->trashed()) {
                $courier->restore();
            }
            $courier->update([
                'name' => $user->name,
                'is_active' => $user->is_active,
            ]);
        }
    }

    private function courierCodeFor(User $user): string
    {
        $base = 'KUR-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);
        $code = $base;
        $suffix = 1;

        while (Courier::withTrashed()->where('courier_code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    private function ensureSuperAdminRemainsActive(User $user, bool $active): void
    {
        if ($active || ! $user->hasRole('Super Admin')) {
            return;
        }

        $activeSuperAdmins = User::role('Super Admin')->where('is_active', true)->count();
        if ($activeSuperAdmins <= 1) {
            throw ValidationException::withMessages(['action' => 'Super Admin aktif terakhir tidak dapat dinonaktifkan.']);
        }
    }

    private function transactionUsage(User $user): array
    {
        $checks = [
            'invoice' => ['invoices', 'created_by'],
            'pembayaran' => ['payments', 'created_by'],
            'barang masuk' => ['incoming_transactions', 'created_by'],
            'pergerakan stok' => ['stock_movements', 'created_by'],
            'kas' => ['cash_transactions', 'created_by'],
            'deposito ongkir' => ['courier_shipping_deposits', 'created_by'],
            'komisi faktur' => ['facture_commissions', 'created_by'],
            'faktur' => ['combined_invoice_documents', 'created_by'],
        ];

        return collect($checks)
            ->filter(fn (array $check) => Schema::hasTable($check[0])
                && Schema::hasColumn($check[0], $check[1])
                && DB::table($check[0])->where($check[1], $user->id)->exists())
            ->keys()->all();
    }
}
