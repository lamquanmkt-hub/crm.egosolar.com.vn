<?php
declare(strict_types=1);
namespace App\Services\Push;
use App\DTO\PushSubscriptionData;
use Illuminate\Contracts\Auth\Authenticatable;
final class UserModelPushSubscriptionGateway implements PushSubscriptionGateway
{
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
    public function unsubscribe(Authenticatable $user, string $endpoint): void
    {
        $user->deletePushSubscription($endpoint);
    }
}
