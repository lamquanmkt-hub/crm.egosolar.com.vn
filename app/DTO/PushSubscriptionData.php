<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class PushSubscriptionData
{
    public function __construct(
        public string $endpoint,
        public string $publicKey,
        public string $authToken,
        public ?string $contentEncoding,
    ) {}
}
