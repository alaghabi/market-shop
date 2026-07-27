<?php

namespace App\ApiResource\UserShop;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\UserShop\UserShopOutput;
use App\State\User\UserShopProvider;
use App\State\User\UserShopProcessor;

#[ApiResource(
    shortName: 'UserShop',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/user-shops',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            output: UserShopOutput::class,
            provider: UserShopProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/boutique-admins',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: UserShopOutput::class,
            provider: UserShopProvider::class,
        ),
        new Post(
            uriTemplate: '/admin/user-shops',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            output: UserShopOutput::class,
            processor: UserShopProcessor::class,
        ),
        new Get(
            uriTemplate: '/admin/user-shops/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            output: UserShopOutput::class,
            provider: UserShopProvider::class,
        ),
        new Patch(
            uriTemplate: '/admin/user-shops/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            read: false,
            output: UserShopOutput::class,
            processor: UserShopProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/user-shops/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            processor: UserShopProcessor::class,
        ),
    ],
)]
final class UserShopResource
{
    public ?string $id = null;
    public ?string $userId = null;
    public ?string $boutiqueId = null;
    public ?string $role = null;
    public ?string $status = null;
}
