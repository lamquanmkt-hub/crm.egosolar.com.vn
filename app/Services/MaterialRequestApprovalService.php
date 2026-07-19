<?php
declare(strict_types=1);
namespace App\Services;
use App\Domain\MaterialRequest\States\MaterialRequestStateFactory;
use App\Models\Projects\MaterialRequest;

final class MaterialRequestApprovalService
{
    public function accountingApprove(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->accountingApprove($mr);
    }
    public function accountingReject(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->accountingReject($mr);
    }
    public function adminApprove(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->adminApprove($mr);
    }
    public function adminReject(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->adminReject($mr);
    }
    public function export(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->export($mr);
    }
}
