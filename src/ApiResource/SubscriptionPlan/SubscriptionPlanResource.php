<?php

namespace App\ApiResource\SubscriptionPlan;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\SubscriptionPlan\SubscriptionPlanInput;
use App\Dto\SubscriptionPlan\SubscriptionPlanOutput;
use App\State\SubscriptionPlan\SubscriptionPlanProcessor;
use App\State\SubscriptionPlan\SubscriptionPlanProvider;

#[ApiResource(
    shortName: 'SubscriptionPlan',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/subscription-plans',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: SubscriptionPlanOutput::class,
            provider: SubscriptionPlanProvider::class,
        ),
        new GetCollection(
            name: 'boutique_subscription_plans',
            uriTemplate: '/boutique/subscription-plans',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            output: SubscriptionPlanOutput::class,
            provider: SubscriptionPlanProvider::class,
        ),
        new Post(
            uriTemplate: '/admin/subscription-plans',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            read: false,
            input: SubscriptionPlanInput::class,
            output: SubscriptionPlanOutput::class,
            processor: SubscriptionPlanProcessor::class,
        ),
        new Get(
            uriTemplate: '/admin/subscription-plans/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: SubscriptionPlanOutput::class,
            provider: SubscriptionPlanProvider::class,
        ),
        new Patch(
            uriTemplate: '/admin/subscription-plans/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: SubscriptionPlanInput::class,
            output: SubscriptionPlanOutput::class,
            provider: SubscriptionPlanProvider::class,
            processor: SubscriptionPlanProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/subscription-plans/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            provider: SubscriptionPlanProvider::class,
            processor: SubscriptionPlanProcessor::class,
        ),
    ],
)]
final class SubscriptionPlanResource
{
    public ?string $id = null;
    public string $name;
    public ?string $description = null;
    public int $durationMonths;
    public int $priceTnd = 0;
    public bool $isFree = false;
    public bool $isVisible = true;
    public bool $isActive = true;
    /** @var list<string>|null */
    public ?array $modules = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
