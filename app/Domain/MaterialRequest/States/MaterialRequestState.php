<?php

declare(strict_types=1);

namespace App\Domain\MaterialRequest\States;

use App\Models\Projects\MaterialRequest;

interface MaterialRequestState
{
    public function accountingApprove(MaterialRequest $mr): void;

    public function accountingReject(MaterialRequest $mr): void;

    public function adminApprove(MaterialRequest $mr): void;

    public function adminReject(MaterialRequest $mr): void;

    public function export(MaterialRequest $mr): void;
}
