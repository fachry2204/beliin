<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        if (request()->user()->hasRole('Kurir')) {
            return redirect()->route('courier.tasks.index');
        }

        $this->authorize('dashboard.view');
        $data = Cache::remember('dashboard.metrics', 60, function () {
            $monthly = Invoice::whereMonth('invoice_date', now()->month)->whereYear('invoice_date', now()->year);
            $chart = collect(range(11, 0))->map(function ($offset) {
                $month = now()->subMonths($offset);

                return ['period' => $month->translatedFormat('M y'), 'total' => Invoice::whereYear('invoice_date', $month->year)->whereMonth('invoice_date', $month->month)->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])->sum('grand_total')];
            });

            return [
                'metrics' => [
                    'sales' => Invoice::whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])->sum('grand_total'),
                    'receivables' => Invoice::whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])->sum('remaining_amount'),
                    'monthly' => (clone $monthly)->count(), 'profit' => (clone $monthly)->sum('gross_profit'),
                ],
                'recent' => Invoice::with('customer:id,name,company_name')->latest('invoice_date')->limit(8)->get(),
                'chart' => $chart,
            ];
        });

        return Inertia::render('Dashboard', $data);
    }
}
