<?php

namespace App\Message;

final readonly class DispatchNotificationMessage
{
    /** @param array<string, string|int|float|bool|null> $variables */
    public function __construct(
        public ?string $boutiqueId,
        public string $eventCode,
        public string $channel,
        public string $recipient,
        public array $variables = [],
        public ?string $orderId = null,
        public ?string $deduplicationKey = null,
    ) {
    }
}
