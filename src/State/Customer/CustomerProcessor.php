<?php

namespace App\State\Customer;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Customer\CustomerResource;
use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Security\BoutiqueContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<CustomerResource> */
final readonly class CustomerProcessor implements ProcessorInterface
{
    public function __construct(
        private CustomerRepository $customers,
        private BoutiqueContext $context,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CustomerResource
    {
        unset($context);

        $customer = $this->customers->find((string) ($uriVariables['id'] ?? ''));
        if (!$customer instanceof Customer) {
            throw new NotFoundHttpException('Customer not found');
        }
        if (!$this->context->canAccessBoutique($customer->getBoutique())) {
            throw new AccessDeniedHttpException('Customer access denied');
        }

        if ($operation instanceof Delete) {
            $customer->delete();
            $this->em->flush();

            return null;
        }

        if ($data instanceof CustomerResource) {
            if ($data->active) {
                $customer->restore();
            } else {
                $customer->delete();
            }
            if (null !== $data->firstName) {
                $customer->setFirstName($data->firstName);
            }
            if (null !== $data->lastName) {
                $customer->setLastName($data->lastName);
            }
            if (null !== $data->phone) {
                $customer->setPhone($data->phone);
            }
        }

        $this->em->flush();

        $output = new CustomerResource();
        $output->id = (string) $customer->getId();
        $output->boutiqueId = (string) $customer->getBoutique()->getId();
        $output->email = $customer->getEmail();
        $output->firstName = $customer->getFirstName();
        $output->lastName = $customer->getLastName();
        $output->phone = $customer->getPhone();
        $output->active = !$customer->isDeleted();

        return $output;
    }
}
