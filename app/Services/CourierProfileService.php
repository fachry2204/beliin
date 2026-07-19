<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Support\Collection;

class CourierProfileService
{
    public function sync(User $user, ?string $role = null): ?Courier
    {
        $isCourier = $role !== null ? $role === 'Kurir' : $user->hasRole('Kurir');
        $courier = Courier::withTrashed()->where('user_id', $user->id)->first();

        if (! $isCourier) {
            $courier?->update([
                'is_active' => false,
                'is_online' => false,
            ]);

            return $courier;
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
                ...($user->is_active ? [] : ['is_online' => false]),
            ]);
        }

        return $courier;
    }

    /**
     * Membuat profil untuk pengguna Kurir lama yang belum tersinkronisasi dan
     * menonaktifkan profil tertaut bila role Kurir sudah dicabut.
     */
    public function reconcileRoleUsers(): void
    {
        /** @var Collection<int, User> $courierUsers */
        $courierUsers = User::role('Kurir')->get(['id', 'name', 'is_active']);

        $courierUsers->each(fn (User $user) => $this->sync($user, 'Kurir'));

        $activeUserIds = $courierUsers->pluck('id');
        $profilesWithoutCourierRole = Courier::query()->whereNotNull('user_id');

        if ($activeUserIds->isEmpty()) {
            $profilesWithoutCourierRole->update(['is_active' => false, 'is_online' => false]);

            return;
        }

        $profilesWithoutCourierRole
            ->whereNotIn('user_id', $activeUserIds)
            ->update(['is_active' => false, 'is_online' => false]);
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
