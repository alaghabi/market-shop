<?php

namespace App\State\Payment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Payment\PaymentMethodOutput;
use App\Entity\PaymentMethod;
use App\Repository\PaymentMethodRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<PaymentMethodOutput> */
final readonly class PaymentMethodProvider implements ProviderInterface
{
    public function __construct(
        private PaymentMethodRepository $methods,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|PaymentMethodOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);

        if (isset($uriVariables['id'])) {
            $method = $this->methods->find((string) $uriVariables['id']);

            return $method instanceof PaymentMethod ? $this->toOutput($method) : null;
        }

        $result = $this->methods->findForBackoffice(
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map([$this, 'toOutput'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function toOutput(PaymentMethod $method): PaymentMethodOutput
    {
        $output = new PaymentMethodOutput();
        $output->id = (string) $method->getId();
        $output->name = $method->getName();
        $output->code = $method->getCode();
        $output->description = $method->getDescription();
        $output->logo = $method->getLogo();
        $output->type = $method->getType()->value;
        $output->isActive = $method->isActive();
        $output->isVisible = $method->isVisible();
        $output->createdAt = $method->getCreatedAt();
        $output->updatedAt = $method->getUpdatedAt();

        return $output;
    }
}
