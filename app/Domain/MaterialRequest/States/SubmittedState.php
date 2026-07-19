<?php
declare(strict_types=1);
namespace App\Domain\MaterialRequest\States;
use App\Enums\MaterialRequestStatus;
use App\Models\Projects\MaterialRequest;
use DomainException;

final class SubmittedState implements MaterialRequestState
{
    public function accountingApprove(MaterialRequest $mr): void
    {
        $mr->update(['status' => MaterialRequestStatus::ACC_APPROVED]);
    }
    public function accountingReject(MaterialRequest $mr): void
    {
        $mr->update(['status' => MaterialRequestStatus::ACC_REJECTED]);
    }
    public function adminApprove(MaterialRequest $mr): void
    {
        throw new DomainException('Chưa tới bước admin duyệt.');
    }
    public function adminReject(MaterialRequest $mr): void
    {
        throw new DomainException('Chưa tới bước admin duyệt.');
    }
    public function export(MaterialRequest $mr): void
    {
        throw new DomainException('Không thể xuất kho.');
    }
}
