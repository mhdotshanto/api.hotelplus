<?php
namespace App\Enums;

enum LedgerType: string
{
    case GENERAL    = 'general';
    case BANK       = 'bank';
    case SALE       = 'sale';
    case CASHBOOK   = 'cashbook';
    case PARTY      = 'party';
}
