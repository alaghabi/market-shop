<?php

namespace App\ApiResource\BoutiquePublicationRequest;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\BoutiquePublicationRequest\BoutiquePublicationDecisionInput;
use App\Dto\BoutiquePublicationRequest\BoutiquePublicationRequestInput;
use App\Dto\BoutiquePublicationRequest\BoutiquePublicationRequestOutput;
use App\State\BoutiquePublicationRequest\BoutiquePublicationRequestProcessor;
use App\State\BoutiquePublicationRequest\BoutiquePublicationRequestProvider;

#[ApiResource(
    shortName: 'BoutiquePublicationRequest',
    operations: [
        new GetCollection(
            uriTemplate: '/boutique/publication-requests',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            output: BoutiquePublicationRequestOutput::class,
            provider: BoutiquePublicationRequestProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/boutique-publication-requests',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: BoutiquePublicationRequestOutput::class,
            provider: BoutiquePublicationRequestProvider::class,
        ),
        new Post(
            uriTemplate: '/boutique/publication-requests',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            read: false,
            input: BoutiquePublicationRequestInput::class,
            output: BoutiquePublicationRequestOutput::class,
            processor: BoutiquePublicationRequestProcessor::class,
        ),
        new Get(
            uriTemplate: '/boutique/publication-requests/{id}',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN')",
            output: BoutiquePublicationRequestOutput::class,
            provider: BoutiquePublicationRequestProvider::class,
        ),
        new Patch(
            name: 'approve_boutique_publication_request',
            uriTemplate: '/admin/boutique-publication-requests/{id}/approve',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            read: false,
            input: BoutiquePublicationDecisionInput::class,
            output: BoutiquePublicationRequestOutput::class,
            processor: BoutiquePublicationRequestProcessor::class,
        ),
        new Patch(
            name: 'reject_boutique_publication_request',
            uriTemplate: '/admin/boutique-publication-requests/{id}/reject',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            read: false,
            input: BoutiquePublicationDecisionInput::class,
            output: BoutiquePublicationRequestOutput::class,
            processor: BoutiquePublicationRequestProcessor::class,
        ),
    ],
)]
final class BoutiquePublicationRequestResource
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public ?string $boutiqueName = null;
    public string $status = 'pending';
    public ?string $requestedAt = null;
    public ?string $handledAt = null;
    public ?string $handledBy = null;
    public ?string $reason = null;
}
