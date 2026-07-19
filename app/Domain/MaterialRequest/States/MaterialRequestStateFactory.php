<?php
declare(strict_types=1);
namespace App\Domain\MaterialRequest\States;
use App\Enums\MaterialRequestStatus;
use App\Models\Projects\MaterialRequest;
use DomainException;

final class MaterialRequestStateFactory
{
    public static function make(MaterialRequest $mr): MaterialRequestState
    {
        return match ($mr->status) {
            MaterialRequestStatus::SUBMITTED => new SubmittedState(),
            MaterialRequestStatus::ACC_APPROVED => new AccApprovedState(),
            MaterialRequestStatus::ADMIN_APPROVED => new AdminApprovedState(),
            default => throw new DomainException('Trạng thái không hỗ trợ xử lý duyệt.'),
        };
    }
}
