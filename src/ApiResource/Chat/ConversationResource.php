<?php

namespace App\ApiResource\Chat;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\State\Chat\ConversationProvider;
use App\State\Chat\ConversationProcessor;

#[ApiResource(
    shortName: 'Conversation',
    operations: [
        new GetCollection(
            uriTemplate: '/conversations',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
            provider: ConversationProvider::class,
            paginationItemsPerPage: 20,
        ),
        new Post(
            uriTemplate: '/conversations',
            security: "is_granted('PUBLIC_ACCESS')",
            processor: ConversationProcessor::class,
        ),
        new Get(
            uriTemplate: '/conversations/{id}',
            security: "is_granted('PUBLIC_ACCESS')",
            provider: ConversationProvider::class,
        ),
        new Patch(
            uriTemplate: '/conversations/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
            processor: ConversationProcessor::class,
        ),
        new Delete(
            uriTemplate: '/conversations/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
            read: false,
            processor: ConversationProcessor::class,
        ),
    ],
)]
final class ConversationResource
{
    public ?string $id = null;
    public string $boutiqueId;
    public ?string $userId = null;
    public ?string $guestName = null;
    public ?string $guestEmail = null;
    public ?string $guestPhone = null;
    public ?string $guestAccessToken = null;
    public bool $active = true;
    public string $createdAt;
    public ?string $updatedAt = null;
    public int $unreadCount = 0;
    public ?array $lastMessage = null;
}
