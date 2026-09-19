<?php

declare(strict_types=1);

namespace App\Enums;

enum MaterialRequestStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case ACC_APPROVED = 'ACC_APPROVED';
    case ACC_REJECTED = 'ACC_REJECTED';
    case ADMIN_APPROVED = 'ADMIN_APPROVED';
    case ADMIN_REJECTED = 'ADMIN_REJECTED';
    case EXPORTED = 'EXPORTED';
    case REJECTED = 'REJECTED';
}
