<?php

namespace App\ApiResource\Boutique;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Boutique\BoutiqueInput;
use App\Dto\Boutique\BoutiqueActionInput;
use App\Dto\Boutique\BoutiqueOutput;
use App\State\Boutique\BoutiqueProcessor;
use App\State\Boutique\BoutiqueProvider;

#[ApiResource(
    shortName: 'Boutique',
    operations: [
        new GetCollection(
            uriTemplate: '/boutiques',
            output: BoutiqueOutput::class,
            provider: BoutiqueProvider::class,
        ),
        new Post(
            uriTemplate: '/boutiques',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            input: BoutiqueInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
        ),
        new Get(
            name: 'get_boutique',
            uriTemplate: '/boutiques/{id}',
            output: BoutiqueOutput::class,
            provider: BoutiqueProvider::class,
        ),
        new Patch(
            uriTemplate: '/boutiques/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            input: BoutiqueInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
        ),
        new Patch(
            name: 'approve_boutique',
            uriTemplate: '/boutiques/{id}/approve',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: BoutiqueActionInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
            provider: BoutiqueProvider::class,
        ),
        new Patch(
            name: 'reject_boutique',
            uriTemplate: '/boutiques/{id}/reject',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: BoutiqueActionInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
            provider: BoutiqueProvider::class,
        ),
        new Patch(
            name: 'suspend_boutique',
            uriTemplate: '/boutiques/{id}/suspend',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: BoutiqueActionInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
            provider: BoutiqueProvider::class,
        ),
        new Patch(
            name: 'activate_boutique',
            uriTemplate: '/boutiques/{id}/activate',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: BoutiqueActionInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
            provider: BoutiqueProvider::class,
        ),
        new Patch(
            name: 'archive_boutique',
            uriTemplate: '/boutiques/{id}/archive',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: BoutiqueActionInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
            provider: BoutiqueProvider::class,
        ),
        new Patch(
            name: 'publish_boutique',
            uriTemplate: '/boutiques/{id}/publish',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
            input: BoutiqueActionInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
            provider: BoutiqueProvider::class,
        ),
        new Patch(
            name: 'unpublish_boutique',
            uriTemplate: '/boutiques/{id}/unpublish',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
            input: BoutiqueActionInput::class,
            output: BoutiqueOutput::class,
            processor: BoutiqueProcessor::class,
            provider: BoutiqueProvider::class,
        ),
        new Delete(
            uriTemplate: '/boutiques/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            processor: BoutiqueProcessor::class,
            provider: BoutiqueProvider::class,
        ),
    ],
    paginationItemsPerPage: 30,
)]
final class BoutiqueResource
{
    public ?string $id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $status = null;
    public ?string $ownerId = null;
    public ?string $description = null;
    public ?string $coverImage = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $website = null;
    public ?string $customDomain = null;
    public bool $isVerified = false;
    public bool $isFeatured = false;
    public bool $isPublished = false;
    public ?string $approvedAt = null;
    public ?string $approvedBy = null;
    public ?string $rejectionReason = null;
    public ?string $primaryColor = null;
    public ?string $secondaryColor = null;
    public ?string $domain = null;
    public ?string $logoUrl = null;
    public ?string $contactEmail = null;
    public ?string $contactPhone = null;
    public ?string $address = null;
    public array $socialLinks = [];
    public ?string $metaPixelId = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTimeImmutable $updatedAt = null;
    public int $usersCount = 0;
    public int $productsCount = 0;
    public int $ordersCount = 0;
    public float $totalRevenue = 0.0;
    public bool $hasActiveSubscription = false;
    public bool $isVisiblePublicly = false;

    public array $colorPalette = [];
    public ?string $fontFamily = null;
    public ?string $fontSize = null;
    public ?string $borderRadius = null;
    public array $iconSet = [];
    public array $headerConfig = [];
    public array $footerConfig = [];
    public array $navigationItems = [];
    public array $frontOfficePages = [];
    public array $featuredCategories = [];
}
