<?php

namespace App\Enums;

enum ProductStatusFilter: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Trashed = 'trashed';
}
