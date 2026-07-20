<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderReturn;
use App\Models\User;

/**
 * Hợp đồng Quy trình trả hàng: tạo, trình duyệt, duyệt/từ chối, nhận hàng và kiểm định.
 *
 * Sinh từ implementation OrderReturnService của chính dự án này (KHÔNG copy từ crm-shop —
 * signature hai codebase đã phân kỳ).
 */
interface OrderReturnServiceInterface
{
    public function create(Order $order, array $data, User $user): OrderReturn;

    public function submit(OrderReturn $return, User $user): OrderReturn;

    public function approve(OrderReturn $return, User $user, ?string $comment = null): OrderReturn;

    public function reject(OrderReturn $return, User $user, string $comment): OrderReturn;

    public function requestRevision(OrderReturn $return, User $user, string $comment): OrderReturn;

    public function receive(OrderReturn $return, User $user, array $quantities): OrderReturn;

    public function inspect(OrderReturn $return, User $user, array $rows): OrderReturn;

    public function transition(OrderReturn $return, string $to, string $action, ?string $note, User $user, array $extra = []): OrderReturn;

    public function history(OrderReturn $return, ?string $from, string $to, string $action, ?string $note, User $user): void;
}
