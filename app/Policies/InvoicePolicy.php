<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.create') && $invoice->status !== InvoiceStatus::Cancelled;
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.issue') && $invoice->status === InvoiceStatus::Draft;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.cancel') && $invoice->status !== InvoiceStatus::Cancelled;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin'])
            && $user->can('invoices.delete');
    }

    public function print(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.print');
    }
}
