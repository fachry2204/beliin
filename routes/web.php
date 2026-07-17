<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AppManifestController;
use App\Http\Controllers\CashTransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CombinedInvoiceController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\CourierPortalController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FactureCommissionController;
use App\Http\Controllers\IncomingTransactionController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/manifest.webmanifest', AppManifestController::class)->name('app.manifest');
Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('couriers', CourierController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('courier-map', [CourierController::class, 'map'])->name('couriers.map');
    Route::post('couriers/{courier}/shipping-deposits/{deposit}/pay', [CourierController::class, 'payShippingDeposit'])->name('couriers.shipping-deposits.pay');
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('incoming-transactions', [IncomingTransactionController::class, 'index'])->name('incoming.index');
    Route::get('incoming-transactions/create', [IncomingTransactionController::class, 'create'])->name('incoming.create');
    Route::post('incoming-transactions', [IncomingTransactionController::class, 'store'])->name('incoming.store');
    Route::post('incoming-transactions/{incomingTransaction}/finalize', [IncomingTransactionController::class, 'finalize'])->name('incoming.finalize');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::put('invoices/{invoice}/shipping', [InvoiceController::class, 'updateShipping'])->name('invoices.shipping.update');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    Route::get('combined-invoices', [CombinedInvoiceController::class, 'index'])->name('combined-invoices.index');
    Route::get('combined-invoices/create', [CombinedInvoiceController::class, 'create'])->name('combined-invoices.create');
    Route::post('combined-invoices', [CombinedInvoiceController::class, 'store'])->name('combined-invoices.store');
    Route::get('combined-invoices/{combinedInvoice}/edit', [CombinedInvoiceController::class, 'edit'])->name('combined-invoices.edit');
    Route::put('combined-invoices/{combinedInvoice}', [CombinedInvoiceController::class, 'update'])->name('combined-invoices.update');
    Route::delete('combined-invoices/{combinedInvoice}', [CombinedInvoiceController::class, 'destroy'])->name('combined-invoices.destroy');
    Route::get('combined-invoices/{combinedInvoice}', [CombinedInvoiceController::class, 'show'])->name('combined-invoices.show');
    Route::put('combined-invoices/{combinedInvoice}/due-date', [CombinedInvoiceController::class, 'updateDueDate'])->name('combined-invoices.due-date.update');
    Route::get('combined-invoices/{combinedInvoice}/print', [CombinedInvoiceController::class, 'print'])->name('combined-invoices.print');
    Route::get('combined-invoices/{combinedInvoice}/pdf', [CombinedInvoiceController::class, 'pdf'])->name('combined-invoices.pdf');
    Route::post('combined-invoices/{combinedInvoice}/payments', [CombinedInvoiceController::class, 'pay'])->name('combined-invoices.pay');
    Route::put('combined-invoices/{combinedInvoice}/payments/{payment}', [CombinedInvoiceController::class, 'updatePayment'])->name('combined-invoices.payments.update');
    Route::get('facture-commissions', [FactureCommissionController::class, 'index'])->name('facture-commissions.index');
    Route::get('facture-commissions/{factureCommission}', [FactureCommissionController::class, 'show'])->name('facture-commissions.show');
    Route::post('facture-commissions/{factureCommission}/pay', [FactureCommissionController::class, 'pay'])->name('facture-commissions.pay');
    Route::put('facture-commissions/{factureCommission}', [FactureCommissionController::class, 'update'])->name('facture-commissions.update');
    Route::delete('facture-commissions/{factureCommission}', [FactureCommissionController::class, 'destroy'])->name('facture-commissions.destroy');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('receivables', [PaymentController::class, 'receivables'])->name('receivables.index');

    Route::get('cash-in', [CashTransactionController::class, 'incoming'])->name('cash-in.index');
    Route::post('cash-in', [CashTransactionController::class, 'storeIncoming'])->name('cash-in.store');
    Route::put('cash-in/{cashTransaction}', [CashTransactionController::class, 'updateIncoming'])->name('cash-in.update');
    Route::delete('cash-in/{cashTransaction}', [CashTransactionController::class, 'destroyIncoming'])->name('cash-in.destroy');
    Route::get('cash-out', [CashTransactionController::class, 'outgoing'])->name('cash-out.index');
    Route::post('cash-out', [CashTransactionController::class, 'storeOutgoing'])->name('cash-out.store');
    Route::put('cash-out/{cashTransaction}', [CashTransactionController::class, 'updateOutgoing'])->name('cash-out.update');
    Route::delete('cash-out/{cashTransaction}', [CashTransactionController::class, 'destroyOutgoing'])->name('cash-out.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/invoices', [ReportController::class, 'invoices'])->name('reports.invoices');
    Route::get('reports/combined-invoices', [ReportController::class, 'combinedInvoices'])->name('reports.combined-invoices');
    Route::get('reports/cash', [ReportController::class, 'cash'])->name('reports.cash');
    Route::get('reports/margins', [ReportController::class, 'margins'])->name('reports.margins');
    Route::get('reports/export/{format}', [ReportController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('reports.export');
    Route::get('settings/company', [CompanySettingController::class, 'edit'])->name('company.edit');
    Route::put('settings/company', [CompanySettingController::class, 'update'])->name('company.update');
    Route::put('settings/company/roles/{role}', [CompanySettingController::class, 'updateRolePermissions'])->name('company.roles.update');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
    Route::get('activity-logs', ActivityLogController::class)->name('activity.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('courier-portal')->name('courier.')->group(function () {
        Route::get('tasks', [CourierPortalController::class, 'tasks'])->name('tasks.index');
        Route::post('tasks/{delivery}/accept', [CourierPortalController::class, 'accept'])->name('tasks.accept');
        Route::post('tasks/{delivery}/start', [CourierPortalController::class, 'start'])->name('tasks.start');
        Route::post('tasks/{delivery}/complete', [CourierPortalController::class, 'complete'])->name('tasks.complete');
        Route::post('location', [CourierPortalController::class, 'location'])->name('location.store');
        Route::get('location/address', [CourierPortalController::class, 'address'])->name('location.address');
        Route::get('earnings', [CourierPortalController::class, 'earnings'])->name('earnings.index');
        Route::get('profile', [CourierPortalController::class, 'profile'])->name('profile.edit');
        Route::patch('profile', [CourierPortalController::class, 'updateProfile'])->name('profile.update');
    });
});

require __DIR__.'/auth.php';
