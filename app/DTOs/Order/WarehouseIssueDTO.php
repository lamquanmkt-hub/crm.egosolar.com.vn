<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use Carbon\Carbon;

/**
 * Dữ liệu duyệt xuất kho một đơn hàng.
 *
 * Gom 4 tham số rời của luồng approveWarehouseIssue thành một đối tượng có kiểu,
 * thay cho mảng không kiểu truyền xuyên controller → service.
 */
readonly class WarehouseIssueDTO
{
    /** Số tháng bảo hành mặc định khi người dùng không nhập. */
    public const DEFAULT_WARRANTY_MONTHS = 60;

    /**
     * @param  string  $actualShipDate  Ngày xuất kho thực tế (Y-m-d), mốc bắt đầu bảo hành
     * @param  array<int|string, list<int|string>>  $serials  Map order_item_id => danh sách serial_unit_id
     * @param  int  $warrantyMonths  Số tháng bảo hành áp cho serial xuất kho
     * @param  string|null  $shippingNote  Ghi chú kèm theo phiếu xuất
     */
    public function __construct(
        public string $actualShipDate,
        public array $serials,
        public int $warrantyMonths,
        public ?string $shippingNote,
    ) {}

    /**
     * Dựng từ dữ liệu đã validate của request.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            actualShipDate: (string) ($data['actual_ship_date'] ?? Carbon::now()->toDateString()),
            serials: is_array($data['serials'] ?? null) ? $data['serials'] : [],
            warrantyMonths: (int) ($data['warranty_months'] ?? self::DEFAULT_WARRANTY_MONTHS),
            shippingNote: $data['shipping_note'] ?? null,
        );
    }

    /**
     * Payload cho OrderService::shipOrder (giữ đúng khóa mà service đang nhận).
     *
     * @return array<string, mixed>
     */
    public function toShipOrderPayload(): array
    {
        return [
            'shipping_note' => $this->shippingNote,
            'serials' => $this->serials,
        ];
    }
}
