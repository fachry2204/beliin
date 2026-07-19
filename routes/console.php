<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Invoice::query()
        ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid])
        ->whereDate('due_date', '<', today())
        ->update(['status' => InvoiceStatus::Overdue]);
})->dailyAt('00:05')->name('mark-overdue-invoices')->withoutOverlapping();

Schedule::command('backup:run --automatic')->everyMinute()->name('automatic-data-backup')->withoutOverlapping();
