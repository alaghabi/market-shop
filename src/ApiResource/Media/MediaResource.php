<?php

namespace App\ApiResource\Media;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\State\Media\MediaProvider;
use App\State\Media\MediaProcessor;

const BOUTIQUE_MEDIA_URI_VARIABLES = [
    'boutiqueId' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id'),
];

#[ApiResource(
    shortName: 'Media',
    operations: [
        new GetCollection(uriTemplate: '/media', output: \App\Dto\Media\MediaOutput::class, provider: MediaProvider::class),
        new Get(uriTemplate: '/media/{id}', uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], output: \App\Dto\Media\MediaOutput::class, provider: MediaProvider::class),
        new Delete(uriTemplate: '/media/{id}', uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('ROLE_BOUTIQUE_ADMIN')", read: false, processor: MediaProcessor::class),
    ],
)]
final class MediaResource
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public ?string $type = null;
    public ?string $fileName = null;
    public ?string $url = null;
}
