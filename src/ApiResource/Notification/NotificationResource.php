<?php

namespace App\ApiResource\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\State\Notification\NotificationProvider;

#[ApiResource(
    shortName: 'Notification',
    operations: [
        new GetCollection(
            uriTemplate: '/notifications',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_CAISSIER')",
            provider: NotificationProvider::class,
        ),
        new Get(
            uriTemplate: '/notifications/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_CAISSIER')",
            provider: NotificationProvider::class,
        ),
        new Patch(
            uriTemplate: '/notifications/{id}/read',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_CAISSIER')",
            provider: NotificationProvider::class,
            processor: NotificationProvider::class,
            input: false,
        ),
    ],
    provider: NotificationProvider::class,
    paginationItemsPerPage: 20,
)]
final class NotificationResource
{
    public ?string $id = null;
    public ?string $recipientIdentifier = null;
    public string $type;
    public string $title;
    public string $message;
    public ?string $boutiqueId = null;
    public bool $read = false;
    public string $createdAt;
}
