<?php

namespace App\ApiResource\Promotion;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Promotion\PromotionInput;
use App\Dto\Promotion\PromotionOutput;
use App\State\Promotion\PromotionProcessor;
use App\State\Promotion\PromotionProvider;

#[ApiResource(
    shortName: 'Promotion',
    operations: [
        new GetCollection(uriTemplate: '/promotions', output: PromotionOutput::class, provider: PromotionProvider::class),
        new Post(uriTemplate: '/promotions', security: "is_granted('ROLE_BOUTIQUE_ADMIN')", input: PromotionInput::class, output: PromotionOutput::class, processor: PromotionProcessor::class),
        new Get(uriTemplate: '/promotions/{id}', output: PromotionOutput::class, provider: PromotionProvider::class),
        new Patch(uriTemplate: '/promotions/{id}', security: "is_granted('ROLE_BOUTIQUE_ADMIN')", read: false, input: PromotionInput::class, output: PromotionOutput::class, processor: PromotionProcessor::class),
        new Delete(uriTemplate: '/promotions/{id}', security: "is_granted('ROLE_BOUTIQUE_ADMIN')", processor: PromotionProcessor::class),
    ],
)]
final class PromotionResource
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public ?string $name = null;
    public ?string $scope = null;
    public ?string $type = null;
    public int $value = 0;
    public ?string $startsAt = null;
    public ?string $endsAt = null;
    public bool $active = true;
    /** @var list<string> */
    public array $categoryIds = [];
    /** @var list<string> */
    public array $productIds = [];
}
