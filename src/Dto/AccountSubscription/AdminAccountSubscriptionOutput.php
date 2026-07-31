<?php

namespace App\Dto\AccountSubscription;

final class AdminAccountSubscriptionOutput
{
    public string $id;
    public string $userId;
    public string $userEmail;
    public ?string $userDisplayName = null;
    public ?string $planId = null;
    public ?string $planName = null;
    public string $status;
    public ?string $startDate = null;
    public ?string $endDate = null;
    /** @var list<array{code: string, name: string, expiresAt: string|null}> */
    public array $extensions = [];
    public string $createdAt;
}
