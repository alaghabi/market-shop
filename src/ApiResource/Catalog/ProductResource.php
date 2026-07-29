<?php

namespace App\ApiResource\Catalog;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Catalog\ProductInput;
use App\Dto\Catalog\ProductOutput;
use App\State\Catalog\ProductProcessor;
use App\State\Catalog\ProductProvider;

#[ApiResource(
    shortName: 'Product',
    operations: [
        new GetCollection(uriTemplate: '/products', output: ProductOutput::class, provider: ProductProvider::class),
        new Post(uriTemplate: '/products', security: "is_granted('ROLE_BOUTIQUE_ADMIN') and is_granted('PERMISSION', 'product.create')", read: false, input: ProductInput::class, output: ProductOutput::class, processor: ProductProcessor::class),
        new Get(uriTemplate: '/products/{id}', output: ProductOutput::class, provider: ProductProvider::class),
        new Patch(uriTemplate: '/products/{id}', uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('ROLE_BOUTIQUE_ADMIN') and is_granted('PERMISSION', 'product.update')", read: false, input: ProductInput::class, output: ProductOutput::class, processor: ProductProcessor::class),
        new Delete(uriTemplate: '/products/{id}', uriVariables: ['id' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('ROLE_BOUTIQUE_ADMIN') and is_granted('PERMISSION', 'product.delete')", read: false, processor: ProductProcessor::class),
    ],
)]
final class ProductResource
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public ?string $categoryId = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $sku = null;
    public ?string $description = null;
    public int $priceCents = 0;
    public string $currency = 'TND';
    public bool $active = true;
}
