<?php

namespace App\ApiResource\AccountSubscription;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Dto\AccountSubscription\AccountSubscriptionChangePreviewOutput;
use App\Dto\AccountSubscription\AccountSubscriptionInput;
use App\Dto\AccountSubscription\AccountSubscriptionOutput;
use App\State\AccountSubscription\AccountSubscriptionProvider;
use App\State\AccountSubscription\AccountSubscriptionProcessor;

#[ApiResource(
    shortName: 'AccountSubscription',
    operations: [
        new Get(
            uriTemplate: '/account/subscription',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            output: AccountSubscriptionOutput::class,
            provider: AccountSubscriptionProvider::class,
        ),
        new Post(
            uriTemplate: '/account/subscription',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            input: AccountSubscriptionInput::class,
            output: AccountSubscriptionOutput::class,
            processor: AccountSubscriptionProcessor::class,
            name: 'account_subscription_subscribe',
            status: 200,
        ),
        new Post(
            uriTemplate: '/account/subscription/renew',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            read: false,
            input: false,
            output: AccountSubscriptionOutput::class,
            processor: AccountSubscriptionProcessor::class,
            name: 'account_subscription_renew',
            status: 200,
        ),
        new Get(
            uriTemplate: '/account/subscription/change-preview',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            output: AccountSubscriptionChangePreviewOutput::class,
            provider: AccountSubscriptionProvider::class,
            name: 'account_subscription_change_preview',
        ),
    ],
)]
final class AccountSubscriptionResource
{
    public bool $isActive = false;
    public ?string $planId = null;
    public ?string $planName = null;
    public int $renewalPriceTnd = 0;
    public ?int $maxBoutiques = null;
    public int $publishedBoutiques = 0;
}
