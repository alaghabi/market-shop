<?php

namespace App\State\Delivery;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Delivery\BoutiqueDeliveryAccountInput;
use App\Dto\Delivery\BoutiqueDeliveryAccountOutput;
use App\Entity\Boutique;
use App\Entity\BoutiqueDeliveryAccount;
use App\Repository\BoutiqueDeliveryAccountRepository;
use App\Repository\BoutiqueRepository;
use App\Repository\DeliveryCompanyRepository;
use App\Security\BoutiqueContext;
use App\Service\Delivery\DeliveryCredentialFieldSchema;
use App\Service\Delivery\DeliveryEngine;
use App\Service\Delivery\EncryptionService;
use App\State\Common\BoutiqueWriteResolverTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BoutiqueDeliveryAccountProcessor implements ProcessorInterface
{
    use BoutiqueWriteResolverTrait;

    public function __construct(
        private readonly BoutiqueDeliveryAccountRepository $repository,
        private readonly BoutiqueRepository $boutiques,
        private readonly DeliveryCompanyRepository $companyRepository,
        private readonly EntityManagerInterface $em,
        private readonly BoutiqueContext $context,
        private readonly EncryptionService $encryption,
        private readonly DeliveryEngine $engine,
        private readonly DeliveryCredentialFieldSchema $credentialFields,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?BoutiqueDeliveryAccountOutput
    {
        $boutique = $this->resolveBoutiqueForWrite($data, $uriVariables, $context);

        $operationName = $operation->getName() ?? '';

        if ('verify_delivery_account' === $operationName) {
            return $this->verifyAccount($boutique, (string) $uriVariables['id']);
        }

        if ('set_default_delivery_account' === $operationName) {
            return $this->setDefault($boutique, (string) $uriVariables['id']);
        }

        if ($operation instanceof Delete) {
            $entity = $this->findAccount($boutique, (string) $uriVariables['id']);
            $this->em->remove($entity);
            $this->em->flush();

            return null;
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->findAccount($boutique, (string) $uriVariables['id']);
            $this->credentialFields->assertValid($entity->getDeliveryCompany(), $data, $entity);
            $this->applyInput($entity, $data);
        } else {
            $company = $this->companyRepository->find($data->deliveryCompanyId);
            if (!$company) {
                throw new NotFoundHttpException('Delivery company not found');
            }

            $this->credentialFields->assertValid($company, $data);

            $entity = new BoutiqueDeliveryAccount(
                boutique: $boutique,
                deliveryCompany: $company,
                encryptedLogin: $data->login ? $this->encryption->encrypt($data->login) : '',
                encryptedPassword: $data->password ? $this->encryption->encrypt($data->password) : '',
            );
            $this->applySensitiveFields($entity, $data);
            $this->em->persist($entity);

            if (0 === count($boutique->getDeliveryAccounts())) {
                $entity->setIsDefault(true);
            }
        }

        $this->em->flush();

        return $this->toOutput($entity);
    }

    private function applyInput(BoutiqueDeliveryAccount $entity, BoutiqueDeliveryAccountInput $input): void
    {
        if (null !== $input->login || null !== $input->password) {
            $login = $input->login ?: $this->safeDecrypt($entity->getEncryptedLogin());
            $password = $input->password ?: $this->safeDecrypt($entity->getEncryptedPassword());
            $entity->setEncryptedCredentials(
                '' !== $login ? $this->encryption->encrypt($login) : '',
                '' !== $password ? $this->encryption->encrypt($password) : '',
            );
        }

        $this->applySensitiveFields($entity, $input);

        if (null !== $input->isActive) {
            $entity->setActive($input->isActive);
        }

        if (null !== $input->login || null !== $input->password || null !== $input->apiKey || null !== $input->token || null !== $input->secret) {
            $entity->markAsUnverified('Identifiants modifiés, vérification requise');
        }
    }

    private function applySensitiveFields(BoutiqueDeliveryAccount $entity, BoutiqueDeliveryAccountInput $input): void
    {
        if (null !== $input->apiKey) {
            $entity->setEncryptedApiKey('' !== $input->apiKey ? $this->encryption->encrypt($input->apiKey) : null);
        }
        if (null !== $input->token) {
            $entity->setEncryptedToken('' !== $input->token ? $this->encryption->encrypt($input->token) : null);
        }
        if (null !== $input->secret) {
            $entity->setEncryptedSecret('' !== $input->secret ? $this->encryption->encrypt($input->secret) : null);
        }
        if (null !== $input->customBaseUrl) {
            $entity->setCustomBaseUrl('' !== $input->customBaseUrl ? $input->customBaseUrl : null);
        }
    }

    private function safeDecrypt(string $value): string
    {
        if ('' === $value) {
            return '';
        }
        try {
            return $this->encryption->decrypt($value);
        } catch (\RuntimeException) {
            return '';
        }
    }

    private function verifyAccount(Boutique $boutique, string $id): BoutiqueDeliveryAccountOutput
    {
        $entity = $this->findAccount($boutique, $id);

        $result = $this->engine->testConnection($entity);

        if ($result->success) {
            $entity->markAsVerified();
        } else {
            $entity->markAsUnverified($result->errorMessage ?? 'Échec vérification');
        }

        $this->em->flush();

        return $this->toOutput($entity);
    }

    private function setDefault(Boutique $boutique, string $id): BoutiqueDeliveryAccountOutput
    {
        $entity = $this->findAccount($boutique, $id);

        $this->repository->clearDefaultForBoutique($boutique);
        $entity->setIsDefault(true);
        $this->em->flush();

        return $this->toOutput($entity);
    }

    private function findAccount(Boutique $boutique, string $id): BoutiqueDeliveryAccount
    {
        $entity = $this->repository->find($id);
        if (!$entity || $entity->getBoutique()->getId() !== $boutique->getId()) {
            throw new NotFoundHttpException('Delivery account not found');
        }

        return $entity;
    }

    private function toOutput(BoutiqueDeliveryAccount $entity): BoutiqueDeliveryAccountOutput
    {
        $output = new BoutiqueDeliveryAccountOutput();
        $output->id = (string) $entity->getId();
        $output->deliveryCompanyId = (string) $entity->getDeliveryCompany()->getId();
        $output->deliveryCompanyName = $entity->getDeliveryCompany()->getName();
        $output->isVerified = $entity->isVerified();
        $output->verifiedAt = $entity->getVerifiedAt()?->format('c');
        $output->lastError = $entity->getLastError();
        $output->isActive = $entity->isActive();
        $output->isDefault = $entity->isDefault();
        $output->hasApiKey = null !== $entity->getEncryptedApiKey();
        $output->hasToken = null !== $entity->getEncryptedToken();
        $output->hasSecret = null !== $entity->getEncryptedSecret();
        $output->customBaseUrl = $entity->getCustomBaseUrl();
        $output->createdAt = $entity->getCreatedAt()->format('c');

        return $output;
    }
}
