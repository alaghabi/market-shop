<?php

namespace App\ApiResource\Catalog;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Catalog\CategoryInput;
use App\Dto\Catalog\CategoryOutput;
use App\State\Catalog\CategoryProcessor;
use App\State\Catalog\CategoryProvider;

#[ApiResource(
    shortName: 'Category',
    operations: [
        new GetCollection(uriTemplate: '/categories', output: CategoryOutput::class, provider: CategoryProvider::class),
        new Post(uriTemplate: '/categories', security: "is_granted('ROLE_BOUTIQUE_ADMIN') and is_granted('PERMISSION', 'product.category.manage')", read: false, input: CategoryInput::class, output: CategoryOutput::class, processor: CategoryProcessor::class),
        new Get(uriTemplate: '/categories/{id}', output: CategoryOutput::class, provider: CategoryProvider::class),
        new Patch(uriTemplate: '/categories/{id}', uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('ROLE_BOUTIQUE_ADMIN') and is_granted('PERMISSION', 'product.category.manage')", read: false, input: CategoryInput::class, output: CategoryOutput::class, processor: CategoryProcessor::class),
        new Delete(uriTemplate: '/categories/{id}', uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('ROLE_BOUTIQUE_ADMIN') and is_granted('PERMISSION', 'product.category.manage')", read: false, processor: CategoryProcessor::class),
    ],
)]
final class CategoryResource
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public ?string $parentId = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $homepageDisplayType = null;
}
