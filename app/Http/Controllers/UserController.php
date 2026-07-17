<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Courier;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
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
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        DB::transaction(function () use ($user, $data, $role) {
            $user->update($data);
            $user->syncRoles([$role]);
            $this->syncCourierProfile($user, $role);
        });
        $this->audit->record('update', 'user', $user, $old, $user->fresh('roles')->toArray());

        return back()->with('success', 'Pengguna diperbarui.');
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
}
