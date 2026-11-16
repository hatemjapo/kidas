<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
