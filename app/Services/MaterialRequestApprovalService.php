<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\MaterialRequest\States\MaterialRequestStateFactory;
use App\Models\Projects\MaterialRequest;

/**
 * Service duyệt đơn vật tư theo state machine (kế toán, admin, xuất kho).
 */
final class MaterialRequestApprovalService
{
    /**
     * Kế toán duyệt đơn vật tư.
     */
    public function accountingApprove(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->accountingApprove($mr);
    }

    /**
     * Kế toán từ chối đơn vật tư.
     */
    public function accountingReject(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->accountingReject($mr);
    }

    /**
     * Admin duyệt đơn vật tư.
     */
    public function adminApprove(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->adminApprove($mr);
    }

    /**
     * Admin từ chối đơn vật tư.
     */
    public function adminReject(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->adminReject($mr);
    }

    /**
     * Xuất kho đơn vật tư.
     */
    public function export(MaterialRequest $mr): void
    {
        MaterialRequestStateFactory::make($mr)
            ->export($mr);
    }
}
