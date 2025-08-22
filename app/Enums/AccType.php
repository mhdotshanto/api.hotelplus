<?php
namespace App\Enums;

enum AccType: string
{
    case ACC_HEAD   = 'acc_head';
    case BANK       = 'bank';
    case SALE       = 'sale';
    case PARTY      = 'party';
    case DISCOUNT   = 'discount';
    case ADJUSTMENT = 'adjustment';
}
