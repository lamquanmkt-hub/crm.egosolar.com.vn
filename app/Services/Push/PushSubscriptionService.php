<?php
declare(strict_types=1);
namespace App\Services\Push;
use App\DTO\PushSubscriptionData;
use Illuminate\Contracts\Auth\Authenticatable;
final class PushSubscriptionService
{
    public function __construct(private readonly PushSubscriptionGateway $gateway)
    {
    }
    public function subscribe(Authenticatable $user, PushSubscriptionData $data): void
    {
        // Có thể chuẩn hóa/trim nếu cần
        $data = new PushSubscriptionData(
            endpoint: trim($data->endpoint),
            publicKey: trim($data->publicKey),
            authToken: trim($data->authToken),
            contentEncoding: $data->contentEncoding ? trim($data->contentEncoding) : null
        );
        // Idempotent: gateway (updatePushSubscription) thường tự upsert theo endpoint
        $this->gateway->subscribe($user, $data);
    }
    public function unsubscribe(Authenticatable $user, string $endpoint): void
    {
        $this->gateway->unsubscribe($user, trim($endpoint));
    }
}
