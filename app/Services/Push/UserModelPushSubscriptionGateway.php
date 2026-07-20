<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\DTO\PushSubscriptionData;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Gateway lưu subscription push qua trait WebPush trên model User.
 */
final class UserModelPushSubscriptionGateway implements PushSubscriptionGateway
{
    /**
     * Lưu subscription push bằng updatePushSubscription của trait trên User.
     */
    public function subscribe(Authenticatable $user, PushSubscriptionData $data): void
    {
        // giữ nguyên cơ chế hiện tại của package/trait
        $user->updatePushSubscription(
            $data->endpoint,
            $data->publicKey,
            $data->authToken,
            $data->contentEncoding
        );
    }

    /**
     * Xoá subscription push theo endpoint trên model User.
     */
    public function unsubscribe(Authenticatable $user, string $endpoint): void
    {
        $user->deletePushSubscription($endpoint);
    }
}
