<?php

namespace App\ApiResource\AccountSubscription;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Dto\AccountSubscription\AdminAccountSubscriptionInput;
use App\Dto\AccountSubscription\AdminAccountSubscriptionOutput;
use App\Dto\AccountSubscription\AccountSubscriptionOutput;
use App\State\AccountSubscription\AdminAccountSubscriptionProcessor;
use App\State\AccountSubscription\AdminAccountSubscriptionProvider;

#[ApiResource(
    shortName: 'AdminAccountSubscription',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/account-subscriptions',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: AdminAccountSubscriptionOutput::class,
            provider: AdminAccountSubscriptionProvider::class,
            name: 'admin_account_subscription_collection',
        ),
        new Post(
            uriTemplate: '/admin/account-subscriptions',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: AdminAccountSubscriptionInput::class,
            output: AccountSubscriptionOutput::class,
            processor: AdminAccountSubscriptionProcessor::class,
            name: 'admin_account_subscription_create',
            status: 201,
            read: false,
        ),
    ],
)]
final class AdminAccountSubscriptionResource
{
}
