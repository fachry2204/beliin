<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombinedInvoiceSequence extends Model
{
    protected $fillable = ['year', 'month', 'last_number'];
}
