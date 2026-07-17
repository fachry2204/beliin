<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\InvoiceSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function next(CarbonInterface $date): string
    {
        return DB::transaction(function () use ($date) {
            InvoiceSequence::query()->insertOrIgnore(['year' => $date->year, 'month' => $date->month, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = InvoiceSequence::query()->where(['year' => $date->year, 'month' => $date->month])->lockForUpdate()->firstOrFail();
            $sequence->increment('last_number');
            $prefix = CompanySetting::query()->value('invoice_prefix') ?: 'INV';

            return sprintf('%s/%04d/%02d/%05d', $prefix, $date->year, $date->month, $sequence->last_number);
        });
    }
}
