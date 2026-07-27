<?php

namespace App\ApiResource\Favorite;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\Favorite\ShopFavoriteOutput;
use App\State\Favorite\FavoriteProcessor;
use App\State\Favorite\ShopFavoriteProvider;

#[ApiResource(
    shortName: 'ShopFavorite',
    operations: [
        new GetCollection(uriTemplate: '/favorites/shops', security: "is_granted('PUBLIC_ACCESS')", output: ShopFavoriteOutput::class, provider: ShopFavoriteProvider::class),
        new Post(name: 'favorite_shop_add', uriTemplate: '/favorites/shops/{shopId}', uriVariables: ['shopId' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('PUBLIC_ACCESS')", input: false, output: ShopFavoriteOutput::class, processor: FavoriteProcessor::class),
        new Delete(name: 'favorite_shop_delete', uriTemplate: '/favorites/shops/{shopId}', uriVariables: ['shopId' => new Link(schema: ['type' => 'string', 'format' => 'uuid'], property: 'id')], security: "is_granted('PUBLIC_ACCESS')", input: false, read: false, processor: FavoriteProcessor::class),
    ],
)]
final class ShopFavoriteResource
{
    public ?string $id = null;
    public ?string $shopId = null;
}
