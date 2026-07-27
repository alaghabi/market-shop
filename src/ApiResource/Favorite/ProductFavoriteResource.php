<?php

namespace App\ApiResource\Favorite;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\Favorite\ProductFavoriteOutput;
use App\State\Favorite\FavoriteProcessor;
use App\State\Favorite\ProductFavoriteProvider;

#[ApiResource(
    shortName: 'ProductFavorite',
    operations: [
        new GetCollection(uriTemplate: '/favorites/products', security: "is_granted('PUBLIC_ACCESS')", output: ProductFavoriteOutput::class, provider: ProductFavoriteProvider::class),
        new Post(name: 'favorite_product_add', uriTemplate: '/favorites/products/{productId}', uriVariables: ['productId' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('PUBLIC_ACCESS')", input: false, output: ProductFavoriteOutput::class, processor: FavoriteProcessor::class),
        new Delete(name: 'favorite_product_delete', uriTemplate: '/favorites/products/{productId}', uriVariables: ['productId' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('PUBLIC_ACCESS')", input: false, read: false, processor: FavoriteProcessor::class),
    ],
)]
final class ProductFavoriteResource
{
    public ?string $id = null;
    public ?string $productId = null;
}
