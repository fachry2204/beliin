<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashTransactionSequence extends Model
{
    protected $fillable = ['type', 'year', 'month', 'last_number'];
}
