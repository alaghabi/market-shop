<?php

namespace App\ApiResource\Customer;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Customer\CustomerProvider;
use App\State\Customer\CustomerProcessor;

#[ApiResource(
    shortName: 'Customer',
    operations: [
        new GetCollection(uriTemplate: '/customers', security: "is_granted('ROLE_CAISSIER')"),
        new Post(uriTemplate: '/customers', security: "is_granted('ROLE_CAISSIER')"),
        new Get(uriTemplate: '/customers/{id}', security: "is_granted('ROLE_CAISSIER')"),
        new Patch(uriTemplate: '/customers/{id}', security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')", processor: CustomerProcessor::class),
        new Delete(uriTemplate: '/customers/{id}', security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')", read: false, processor: CustomerProcessor::class),
    ],
    provider: CustomerProvider::class,
    processor: CustomerProcessor::class,
)]
final class CustomerResource
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public ?string $email = null;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $phone = null;
    public bool $active = true;
}
