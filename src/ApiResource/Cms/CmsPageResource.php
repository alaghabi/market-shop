<?php

namespace App\ApiResource\Cms;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Cms\CmsPageProvider;
use App\State\Cms\CmsPageProcessor;

const BOUTIQUE_CMS_URI_VARIABLES = [
    'boutiqueId' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id'),
];

#[ApiResource(
    shortName: 'CmsPage',
    operations: [
        new GetCollection(
            uriTemplate: '/cms/pages',
            output: \App\Dto\Cms\CmsPageOutput::class,
            provider: CmsPageProvider::class,
        ),
        new Post(
            uriTemplate: '/cms/pages',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            input: \App\Dto\Cms\CmsPageInput::class,
            output: \App\Dto\Cms\CmsPageOutput::class,
            processor: CmsPageProcessor::class,
        ),
        new Get(
            uriTemplate: '/cms/pages/{id}',
            uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')],
            output: \App\Dto\Cms\CmsPageOutput::class,
            provider: CmsPageProvider::class,
        ),
        new Patch(
            uriTemplate: '/cms/pages/{id}',
            uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')],
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            read: false,
            input: \App\Dto\Cms\CmsPageInput::class,
            output: \App\Dto\Cms\CmsPageOutput::class,
            processor: CmsPageProcessor::class,
        ),
        new Delete(
            uriTemplate: '/cms/pages/{id}',
            uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')],
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            read: false,
            processor: CmsPageProcessor::class,
        ),
        new Post(
            name: 'publish_cms_page',
            uriTemplate: '/cms/pages/{id}/publish',
            uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')],
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            read: false,
            input: false,
            output: \App\Dto\Cms\CmsPageOutput::class,
            processor: CmsPageProcessor::class,
        ),
        new Post(
            name: 'unpublish_cms_page',
            uriTemplate: '/cms/pages/{id}/unpublish',
            uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')],
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            read: false,
            input: false,
            output: \App\Dto\Cms\CmsPageOutput::class,
            processor: CmsPageProcessor::class,
        ),
        new Get(
            name: 'public_cms_page',
            uriTemplate: '/pages/{slug}',
            uriVariables: ['slug' => new Link(schema: ['type' => 'string'], property: 'slug')],
            security: 'is_granted("PUBLIC_ACCESS")',
            output: \App\Dto\Cms\CmsPageOutput::class,
            provider: CmsPageProvider::class,
        ),
        new Get(
            uriTemplate: '/cms/pages/{id}/blocks/{blockId}',
            uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id'), 'blockId' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')],
            output: \App\Dto\Cms\CmsBlockOutput::class,
            provider: CmsPageProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/cms/pages/{id}/blocks',
            uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')],
            output: \App\Dto\Cms\CmsBlockOutput::class,
            provider: CmsPageProvider::class,
        ),
    ],
)]
final class CmsPageResource
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public ?string $title = null;
    public ?string $slug = null;
}
