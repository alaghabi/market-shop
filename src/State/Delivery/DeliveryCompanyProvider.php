<?php

namespace App\State\Delivery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Delivery\DeliveryCompanyOutput;
use App\Dto\Delivery\DeliveryEndpointOutput;
use App\Entity\DeliveryCompany;
use App\Entity\DeliveryEndpoint;
use App\Repository\DeliveryCompanyRepository;
use App\Service\AppConfigService;
use App\Service\Delivery\DeliveryCredentialFieldSchema;

final class DeliveryCompanyProvider implements ProviderInterface
{
    public function __construct(
        private readonly DeliveryCompanyRepository $repository,
        private readonly AppConfigService $appConfig,
        private readonly DeliveryCredentialFieldSchema $credentialFields,
    ) {
    }

    /** @return array<DeliveryCompanyOutput>|DeliveryCompanyOutput|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|DeliveryCompanyOutput|null
    {
        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);

            return $entity ? $this->toOutput($entity) : null;
        }

        if ('admin_list_delivery_companies' === $operation->getName()) {
            return array_map($this->toOutput(...), $this->repository->findAll());
        }

        if (!$this->appConfig->isModuleEnabled('livraison')) {
            return [];
        }

        $visibleCompanies = $this->appConfig->section('delivery')['visible_companies'] ?? [];
        $visibleCompanies = is_array($visibleCompanies) ? array_map('strval', $visibleCompanies) : [];

        $companies = $this->repository->findActive();
        if ([] !== $visibleCompanies) {
            $companies = array_values(array_filter($companies, fn ($company) => in_array($company->getSlug(), $visibleCompanies, true)));
        }

        return array_map($this->toOutput(...), $companies);
    }

    public function toOutput(DeliveryCompany $entity): DeliveryCompanyOutput
    {
        $output = new DeliveryCompanyOutput();
        $output->id = (string) $entity->getId();
        $output->name = $entity->getName();
        $output->slug = $entity->getSlug();
        $output->baseUrl = $entity->getBaseUrl();
        $output->provider = $entity->getProvider();
        $output->authType = $entity->getAuthType()->value;
        $output->authConfig = $entity->getAuthConfig();
        $output->credentialFields = $this->credentialFields->fieldsFor($entity);
        $output->mappingConfig = $entity->getMappingConfig();
        $output->parametersConfig = $entity->getParametersConfig();
        $output->logoUrl = $entity->getLogoUrl();
        $output->description = $entity->getDescription();
        $output->isActive = $entity->isActive();
        $output->endpoints = array_map($this->toEndpointOutput(...), $entity->getEndpoints()->toArray());
        $output->createdAt = $entity->getCreatedAt()->format('c');
        $output->updatedAt = $entity->getUpdatedAt()?->format('c');

        return $output;
    }

    private function toEndpointOutput(DeliveryEndpoint $endpoint): DeliveryEndpointOutput
    {
        $output = new DeliveryEndpointOutput();
        $output->id = (string) $endpoint->getId();
        $output->companyId = (string) $endpoint->getCompany()->getId();
        $output->type = $endpoint->getType()->value;
        $output->name = $endpoint->getName();
        $output->url = $endpoint->getUrl();
        $output->httpMethod = $endpoint->getHttpMethod()->value;
        $output->headers = $endpoint->getHeaders();
        $output->responseType = $endpoint->getResponseType()->value;
        $output->isActive = $endpoint->isActive();
        $output->createdAt = $endpoint->getCreatedAt()->format('c');
        $output->updatedAt = $endpoint->getUpdatedAt()?->format('c');

        return $output;
    }
}
