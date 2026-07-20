<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\DTO\PushSubscriptionData;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Interface gateway lưu trữ đăng ký web push của người dùng.
 */
interface PushSubscriptionGateway
{
    /**
     * Đăng ký (upsert) subscription push cho người dùng.
     */
    public function subscribe(Authenticatable $user, PushSubscriptionData $data): void;

    /**
     * Huỷ đăng ký subscription push theo endpoint.
     */
    public function unsubscribe(Authenticatable $user, string $endpoint): void;
}
