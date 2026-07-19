<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerCodeService
{
    public function create(array $attributes): Customer
    {
        return DB::transaction(function () use ($attributes): Customer {
            $usesMysqlLock = DB::getDriverName() === 'mysql';

            if ($usesMysqlLock) {
                $lock = DB::selectOne("SELECT GET_LOCK('customer_code_generation', 10) AS acquired");
                if ((int) ($lock->acquired ?? 0) !== 1) {
                    throw new RuntimeException('Gagal memperoleh kunci pembuatan kode pelanggan.');
                }
            }

            try {
                $attributes['customer_code'] = $this->nextCode();

                return Customer::create($attributes);
            } finally {
                if ($usesMysqlLock) {
                    DB::selectOne("SELECT RELEASE_LOCK('customer_code_generation') AS released");
                }
            }
        });
    }

    private function nextCode(): string
    {
        $largestExistingNumber = Customer::withTrashed()
            ->pluck('customer_code')
            ->reduce(function (int $largest, string $code): int {
                return preg_match('/^CUS-(\d+)$/', $code, $matches)
                    ? max($largest, (int) $matches[1])
                    : $largest;
            }, 0);

        return sprintf('CUS-%05d', $largestExistingNumber + 1);
    }
}
