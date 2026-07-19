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
        $data = Cache::remember('dashboard.metrics.v2', 60, function () {
            $monthly = Invoice::whereMonth('invoice_date', now()->month)->whereYear('invoice_date', now()->year);
            $chartStart = now()->startOfMonth()->subMonths(11);
            $chartInvoices = Invoice::query()
                ->whereBetween('invoice_date', [$chartStart->toDateString(), now()->endOfDay()])
                ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
                ->get(['invoice_date', 'grand_total']);
            $chart = collect(range(11, 0))->map(function ($offset) {
                $month = now()->subMonths($offset);

                return ['period' => $month->translatedFormat('M y'), 'key' => $month->format('Y-m')];
            })->map(fn (array $point) => [
                'period' => $point['period'],
                'total' => $chartInvoices
                    ->filter(fn (Invoice $invoice) => $invoice->invoice_date->format('Y-m') === $point['key'])
                    ->sum('grand_total'),
            ]);
            $dailyChart = collect(range(1, now()->day))->map(function (int $day) use ($chartInvoices) {
                $date = now()->startOfMonth()->day($day);

                return [
                    'period' => $date->translatedFormat('d M'),
                    'total' => $chartInvoices
                        ->filter(fn (Invoice $invoice) => $invoice->invoice_date->isSameDay($date))
                        ->sum('grand_total'),
                ];
            });

            return [
                'metrics' => [
                    'sales' => Invoice::whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])->sum('grand_total'),
                    'receivables' => Invoice::whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])->sum('remaining_amount'),
                    'monthly' => (clone $monthly)->count(), 'profit' => (clone $monthly)->sum('gross_profit'),
                ],
                'recent' => Invoice::with('customer:id,name,company_name')->latest('invoice_date')->limit(8)->get(),
                'chart' => $chart,
                'dailyChart' => $dailyChart,
            ];
        });

        return Inertia::render('Dashboard', $data);
    }
}
