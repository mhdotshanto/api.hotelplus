<?php
namespace App\Enums;

enum AccVoucherType: string
{
    case BILL               = 'bill';
    case PAYMENT            = 'payment';
    case REFUND             = 'refund';
    case TIP                = 'tip';
    case ADJUSTMENT         = 'adjustment';
    case DISCOUNT           = 'discount';
    case CANCEL_RESERVATION = 'cancel-reservation';
}
