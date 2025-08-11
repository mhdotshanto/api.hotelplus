<?php
namespace App\Enums;

enum AccVoucherType: string
{
    case BILL       = 'bill';
    case PAYMENT    = 'payment';
    case REFUND     = 'refund';
    case ADJUSTMENT = 'adjustment';
    case DISCOUNT   = 'discount';
}
