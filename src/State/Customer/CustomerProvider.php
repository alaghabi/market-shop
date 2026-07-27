<?php

namespace App\State\Customer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Customer\CustomerResource;
use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<CustomerResource> */
final readonly class CustomerProvider implements ProviderInterface
{
    public function __construct(
        private CustomerRepository $customers,
        private BackofficeScopeResolver $scope,
        private BoutiqueContext $context,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|CustomerResource|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $boutique = $this->scope->resolve($request);

        if (isset($uriVariables['id'])) {
            $customer = $this->customers->find((string) $uriVariables['id']);
            if (!$customer instanceof Customer || null !== $boutique && !$this->belongsTo($customer, $boutique)) {
                return null;
            }

            return $this->toResource($customer);
        }

        if (!$boutique instanceof \App\Entity\Boutique && !$this->context->isSuperAdmin()) {
            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        $result = $this->customers->findForBackoffice(
            $boutique,
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map([$this, 'toResource'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function belongsTo(Customer $customer, \App\Entity\Boutique $boutique): bool
    {
        return (string) $customer->getBoutique()->getId() === (string) $boutique->getId()
            && $this->context->canAccessBoutique($boutique);
    }

    private function toResource(Customer $customer): CustomerResource
    {
        $resource = new CustomerResource();
        $resource->id = (string) $customer->getId();
        $resource->boutiqueId = (string) $customer->getBoutique()->getId();
        $resource->email = $customer->getEmail();
        $resource->firstName = $customer->getFirstName();
        $resource->lastName = $customer->getLastName();
        $resource->phone = $customer->getPhone();
        $resource->active = !$customer->isDeleted();

        return $resource;
    }
}
