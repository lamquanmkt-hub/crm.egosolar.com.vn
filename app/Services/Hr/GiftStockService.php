<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Hr\Gift;
use App\Models\Hr\GiftReceipt;
use App\Models\Hr\GiftRequest;
use App\Models\Hr\GiftStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GiftStockService
{
    public function approveReceipt(GiftReceipt $receipt, User $user): GiftReceipt
    {
        return DB::transaction(function () use ($receipt, $user): GiftReceipt {
            $locked = GiftReceipt::query()
                ->whereKey($receipt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new RuntimeException('Phiếu nhập không còn ở trạng thái chờ duyệt.');
            }

            $items = $locked->items()->with('gift')->get();
            if ($items->isEmpty()) {
                throw new RuntimeException('Phiếu nhập chưa có quà tặng.');
            }

            foreach ($items as $item) {
                $gift = Gift::query()->whereKey($item->gift_id)->lockForUpdate()->firstOrFail();
                $this->ensureSameCompany((int) $locked->company_id, (int) $gift->company_id);
                $this->ensureMovementMissing('receipt', (int) $item->id, 'receipt');

                $quantity = (float) $item->quantity;
                if ($quantity <= 0) {
                    throw new RuntimeException('Số lượng nhập phải lớn hơn 0.');
                }

                $newBalance = round((float) $gift->current_stock + $quantity, 3);
                $gift->forceFill(['current_stock' => $newBalance])->save();

                $this->recordMovement(
                    gift: $gift,
                    movementType: 'receipt',
                    quantity: $quantity,
                    balanceAfter: $newBalance,
                    sourceType: 'receipt',
                    sourceId: (int) $locked->id,
                    sourceItemId: (int) $item->id,
                    sourceCode: (string) $locked->code,
                    description: 'Nhập kho quà tặng theo phiếu '.$locked->code,
                    user: $user,
                );
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'posted_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            return $locked->fresh(['items.gift', 'creator', 'approver']);
        }, 3);
    }

    public function approveRequest(GiftRequest $request, User $user): GiftRequest
    {
        return DB::transaction(function () use ($request, $user): GiftRequest {
            $locked = GiftRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new RuntimeException('Yêu cầu tặng quà không còn ở trạng thái chờ duyệt.');
            }

            if ($locked->stock_deducted_at !== null) {
                throw new RuntimeException('Yêu cầu này đã trừ tồn kho trước đó.');
            }

            $items = $locked->items()->get();
            if ($items->isEmpty()) {
                throw new RuntimeException('Yêu cầu chưa có quà tặng.');
            }

            $lockedGifts = [];
            foreach ($items as $item) {
                $gift = Gift::query()->whereKey($item->gift_id)->lockForUpdate()->firstOrFail();
                $this->ensureSameCompany((int) $locked->company_id, (int) $gift->company_id);

                $quantity = (float) $item->quantity;
                if ($quantity <= 0) {
                    throw new RuntimeException('Số lượng xuất phải lớn hơn 0.');
                }

                if ((float) $gift->current_stock < $quantity) {
                    throw new RuntimeException(
                        sprintf('Quà %s chỉ còn %s %s, không đủ để duyệt.', $gift->name, $gift->current_stock, $gift->unit)
                    );
                }

                $lockedGifts[(int) $item->id] = $gift;
            }

            foreach ($items as $item) {
                /** @var Gift $gift */
                $gift = $lockedGifts[(int) $item->id];
                $this->ensureMovementMissing('request', (int) $item->id, 'issue');

                $quantity = (float) $item->quantity;
                $newBalance = round((float) $gift->current_stock - $quantity, 3);
                $gift->forceFill(['current_stock' => $newBalance])->save();

                $this->recordMovement(
                    gift: $gift,
                    movementType: 'issue',
                    quantity: -$quantity,
                    balanceAfter: $newBalance,
                    sourceType: 'request',
                    sourceId: (int) $locked->id,
                    sourceItemId: (int) $item->id,
                    sourceCode: (string) $locked->code,
                    description: 'Xuất quà cho '.$locked->customer_name.' theo '.$locked->code,
                    user: $user,
                );
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'stock_deducted_at' => now(),
                'delivery_status_updated_by' => $user->id,
                'delivery_status_updated_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            return $locked->fresh(['items.gift', 'customer', 'creator', 'approver']);
        }, 3);
    }

    public function returnRequest(GiftRequest $request, User $user, ?string $note = null): GiftRequest
    {
        return DB::transaction(function () use ($request, $user, $note): GiftRequest {
            $locked = GiftRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['failed', 'delivered'], true)) {
                throw new RuntimeException('Chỉ hoàn kho từ trạng thái giao thất bại hoặc đã giao.');
            }

            if ($locked->stock_deducted_at === null) {
                throw new RuntimeException('Yêu cầu này chưa từng trừ tồn kho.');
            }

            if ($locked->returned_at !== null) {
                throw new RuntimeException('Yêu cầu này đã hoàn kho trước đó.');
            }

            foreach ($locked->items()->get() as $item) {
                $gift = Gift::query()->whereKey($item->gift_id)->lockForUpdate()->firstOrFail();
                $this->ensureSameCompany((int) $locked->company_id, (int) $gift->company_id);
                $this->ensureMovementMissing('request', (int) $item->id, 'return');

                $quantity = (float) $item->quantity;
                $newBalance = round((float) $gift->current_stock + $quantity, 3);
                $gift->forceFill(['current_stock' => $newBalance])->save();

                $this->recordMovement(
                    gift: $gift,
                    movementType: 'return',
                    quantity: $quantity,
                    balanceAfter: $newBalance,
                    sourceType: 'request',
                    sourceId: (int) $locked->id,
                    sourceItemId: (int) $item->id,
                    sourceCode: (string) $locked->code,
                    description: 'Hoàn quà về kho từ '.$locked->code,
                    user: $user,
                );
            }

            $locked->forceFill([
                'status' => 'returned',
                'returned_at' => now(),
                'delivery_note' => trim((string) $note) !== '' ? $note : $locked->delivery_note,
                'delivery_status_updated_by' => $user->id,
                'delivery_status_updated_at' => now(),
            ])->save();

            return $locked->fresh(['items.gift', 'customer', 'creator', 'approver']);
        }, 3);
    }

    private function recordMovement(
        Gift $gift,
        string $movementType,
        float $quantity,
        float $balanceAfter,
        string $sourceType,
        int $sourceId,
        int $sourceItemId,
        string $sourceCode,
        string $description,
        User $user,
    ): void {
        GiftStockMovement::query()->create([
            'company_id' => $gift->company_id,
            'gift_id' => $gift->id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_item_id' => $sourceItemId,
            'source_code' => $sourceCode,
            'description' => $description,
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);
    }

    private function ensureMovementMissing(string $sourceType, int $sourceItemId, string $movementType): void
    {
        $exists = GiftStockMovement::query()
            ->where('source_type', $sourceType)
            ->where('source_item_id', $sourceItemId)
            ->where('movement_type', $movementType)
            ->exists();

        if ($exists) {
            throw new RuntimeException('Giao dịch tồn kho đã được ghi nhận, hệ thống chặn thao tác lặp.');
        }
    }

    private function ensureSameCompany(int $documentCompanyId, int $giftCompanyId): void
    {
        if ($documentCompanyId !== $giftCompanyId) {
            throw new RuntimeException('Dữ liệu quà tặng không cùng công ty.');
        }
    }
}
