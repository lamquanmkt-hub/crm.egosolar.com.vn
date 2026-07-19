<?php

declare(strict_types=1);

namespace App\Enums;

enum MaterialRequestStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';           // Chờ admin duyệt
    case ADMIN_APPROVED = 'ADMIN_APPROVED'; // Chờ kho duyệt
    case EXPORTED = 'EXPORTED';             // Đã xuất kho
    case REJECTED = 'REJECTED';             // Từ chối
}