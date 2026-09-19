<?php

declare(strict_types=1);

namespace App\Actions\MaterialRequest;

use App\Models\Projects\MaterialRequest;
use Illuminate\Support\Facades\DB;

final class SyncItemsAction
{
    public function execute(MaterialRequest $materialRequest, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                'material_request_id' => $materialRequest->id,
                'product_id' => ! empty($item['product_id']) ? (int) $item['product_id'] : null,
                'qty' => (float) ($item['qty'] ?? 0),
                'note' => $item['note'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('material_request_items')->insert($rows);
    }
}
