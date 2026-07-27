<?php

namespace App\ApiResource\Chat;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\Chat\ConversationListProvider;

#[ApiResource(
    shortName: 'ConversationList',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/conversations',
            provider: ConversationListProvider::class,
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 20,
)]
final class ConversationListResource
{
    public string $id;
    public string $boutiqueId;
    public string $boutiqueName;
    public ?string $userDisplayName = null;
    public ?string $guestName = null;
    public ?string $guestEmail = null;
    public ?string $lastMessage = null;
    public ?string $lastMessageAt = null;
    public int $unreadCount = 0;
    public bool $active = true;
    public string $createdAt;
}
