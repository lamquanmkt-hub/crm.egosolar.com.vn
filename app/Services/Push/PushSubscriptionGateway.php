<?php
declare(strict_types=1);
namespace App\Services\Push;
use App\DTO\PushSubscriptionData;
use Illuminate\Contracts\Auth\Authenticatable;
interface PushSubscriptionGateway
{
    public function subscribe(Authenticatable $user, PushSubscriptionData $data): void;
    public function unsubscribe(Authenticatable $user, string $endpoint): void;
}
