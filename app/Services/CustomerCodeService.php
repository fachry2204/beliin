<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSequence;
use Illuminate\Support\Facades\DB;

class CustomerCodeService
{
    public function next(): string
    {
        return DB::transaction(function (): string {
            CustomerSequence::query()->insertOrIgnore([
                'id' => 1,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = CustomerSequence::query()->lockForUpdate()->findOrFail(1);
            $largestExistingNumber = Customer::withTrashed()
                ->pluck('customer_code')
                ->reduce(function (int $largest, string $code): int {
                    return preg_match('/^CUS-(\d+)$/', $code, $matches)
                        ? max($largest, (int) $matches[1])
                        : $largest;
                }, 0);

            $nextNumber = max($sequence->last_number, $largestExistingNumber) + 1;
            $sequence->update(['last_number' => $nextNumber]);

            return sprintf('CUS-%05d', $nextNumber);
        });
    }
}
