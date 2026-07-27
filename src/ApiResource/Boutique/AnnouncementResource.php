<?php

namespace App\ApiResource\Boutique;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Boutique\AnnouncementProvider;
use App\State\Boutique\AnnouncementProcessor;

#[ApiResource(
    shortName: 'Announcement',
    operations: [
        new GetCollection(
            uriTemplate: '/announcements',
            itemUriTemplate: '/announcements/{id}',
            output: \App\Dto\Boutique\AnnouncementOutput::class,
            provider: AnnouncementProvider::class,
        ),
        new Post(
            uriTemplate: '/announcements',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            input: \App\Dto\Boutique\AnnouncementInput::class,
            processor: AnnouncementProcessor::class,
        ),
        new Get(
            uriTemplate: '/announcements/{id}',
            uriVariables: [
                'id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id'),
            ],
            output: \App\Dto\Boutique\AnnouncementOutput::class,
            provider: AnnouncementProvider::class,
        ),
        new Patch(
            uriTemplate: '/announcements/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            input: \App\Dto\Boutique\AnnouncementInput::class,
            processor: AnnouncementProcessor::class,
        ),
        new Delete(
            uriTemplate: '/announcements/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            read: false,
            processor: AnnouncementProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/announcements',
            itemUriTemplate: '/announcements/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: \App\Dto\Boutique\AnnouncementOutput::class,
            provider: AnnouncementProvider::class,
        ),
    ],
)]
final class AnnouncementResource
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
}
