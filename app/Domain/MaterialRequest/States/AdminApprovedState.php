<?php
declare(strict_types=1);
namespace App\Domain\MaterialRequest\States;
use App\Enums\MaterialRequestStatus;
use App\Models\Projects\MaterialRequest;
use DomainException;

final class AdminApprovedState implements MaterialRequestState
{
    public function accountingApprove(MaterialRequest $mr): void
    {
        throw new DomainException('Đã qua bước kế toán.');
    }
    public function accountingReject(MaterialRequest $mr): void
    {
        throw new DomainException('Không thể từ chối.');
    }
    public function adminApprove(MaterialRequest $mr): void
    {
        throw new DomainException('Đơn đã được admin duyệt.');
    }
    public function adminReject(MaterialRequest $mr): void
    {
        throw new DomainException('Không thể từ chối.');
    }
    public function export(MaterialRequest $mr): void
    {
        $mr->update(['status' => MaterialRequestStatus::EXPORTED]);
    }
}
