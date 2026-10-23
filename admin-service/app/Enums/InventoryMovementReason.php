<?php

namespace App\Enums;

enum InventoryMovementReason: string
{
    case Initial = 'initial';
    case Restock = 'restock';
    case Sale = 'sale';
    case Cancellation = 'cancellation';
    case Adjustment = 'adjustment';
}
