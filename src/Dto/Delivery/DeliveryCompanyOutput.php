<?php

namespace App\Dto\Delivery;

final class DeliveryCompanyOutput
{
    public string $id;
    public string $name;
    public string $slug;
    public string $baseUrl;
    public string $provider;
    public string $authType;
    /** @var array<string, mixed> */
    public array $authConfig = [];
    /**
     * Convenience projection of authConfig.credentialFields for boutique UIs.
     *
     * @var list<array{key: string, label: string, type: string, required: bool, hint: ?string}>
     */
    public array $credentialFields = [];
    /** @var array<string, mixed> */
    public array $mappingConfig = [];
    /** @var array<string, mixed> */
    public array $parametersConfig = [];
    public ?string $logoUrl;
    public ?string $description;
    public bool $isActive;
    /** @var list<DeliveryEndpointOutput> */
    public array $endpoints = [];
    public string $createdAt;
    public ?string $updatedAt = null;
}
