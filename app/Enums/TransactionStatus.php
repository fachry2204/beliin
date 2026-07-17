<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
    case Cancelled = 'cancelled';
}
