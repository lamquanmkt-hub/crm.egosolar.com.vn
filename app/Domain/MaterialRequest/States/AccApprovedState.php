<?php
declare(strict_types=1);
namespace App\Domain\MaterialRequest\States;
use App\Enums\MaterialRequestStatus;
use App\Models\Projects\MaterialRequest;
use DomainException;

final class AccApprovedState implements MaterialRequestState
{
    public function accountingApprove(MaterialRequest $mr): void
    {
        throw new DomainException('Đơn đã được kế toán duyệt.');
    }
    public function accountingReject(MaterialRequest $mr): void
    {
        throw new DomainException('Không thể từ chối ở bước này.');
    }
    public function adminApprove(MaterialRequest $mr): void
    {
        $mr->update(['status' => MaterialRequestStatus::ADMIN_APPROVED]);
    }
    public function adminReject(MaterialRequest $mr): void
    {
        $mr->update(['status' => MaterialRequestStatus::ADMIN_REJECTED]);
    }
    public function export(MaterialRequest $mr): void
    {
        throw new DomainException('Chưa tới bước xuất kho.');
    }
}
