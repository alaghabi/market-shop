<?php

namespace App\State\AccountSubscription;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AccountSubscription\AccountSubscriptionOutput;
use App\Dto\AccountSubscription\AdminAccountSubscriptionInput;
use App\Entity\AccountSubscription;
use App\Entity\AccountSubscriptionExtension;
use App\Entity\Extension;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Repository\ExtensionRepository;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\UserRepository;
use App\Service\Audit\AuditLogService;
use App\Service\Subscription\AccountSubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<AdminAccountSubscriptionInput, AccountSubscriptionOutput> */
final readonly class AdminAccountSubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private AccountSubscriptionService $service,
        private AccountSubscriptionProvider $provider,
        private SubscriptionPlanRepository $plans,
        private ExtensionRepository $extensions,
        private UserRepository $users,
        private EntityManagerInterface $em,
        private Security $security,
        private AuditLogService $auditLog,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccountSubscriptionOutput
    {
        if (!$data instanceof AdminAccountSubscriptionInput) {
            throw new BadRequestHttpException('Corps de requête invalide.');
        }

        $actor = $this->resolveActor();

        $target = $this->users->find((string) $data->userId);
        if (!$target instanceof User) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }

        $plan = $this->resolvePlan($data->planId);
        $grantedExtensions = $this->resolveExtensions($data->extensionIds);

        $current = $this->service->getActiveSubscription($target);

        $now = new \DateTimeImmutable();
        $entity = new AccountSubscription($target, $plan);
        $entity->activate($actor);

        $startDate = null;
        if (null !== $data->startDate && '' !== (string) $data->startDate) {
            $startDate = new \DateTimeImmutable($data->startDate);
        }
        $endDate = null;
        if (null !== $data->endDate && '' !== (string) $data->endDate) {
            $endDate = new \DateTimeImmutable($data->endDate);
        }

        if (null !== $startDate || null !== $endDate) {
            $entity->activateWithDates($startDate ?? $now, $endDate, $actor);
        }

        $this->em->persist($entity);

        foreach ($grantedExtensions as $extension) {
            $this->em->persist($this->createGrant($entity, $extension, $actor));
        }

        if (null !== $current) {
            $current->markAsReplaced($entity);
        }

        $this->auditLog->log(
            actorEmail: $actor,
            actorRole: 'ROLE_SUPER_ADMIN',
            action: 'admin.account_subscription.created',
            resourceType: 'AccountSubscription',
            resourceId: (string) $entity->getId(),
            details: [
                'targetUserId' => (string) $target->getId(),
                'planId' => (string) $plan->getId(),
                'planName' => $plan->getName(),
                'extensionIds' => array_map(static fn (Extension $e): string => (string) $e->getId(), $grantedExtensions),
            ],
        );

        $this->em->flush();
        $this->service->invalidate($target);

        $output = $this->provider->current($target);
        $output->id = (string) $entity->getId();

        return $output;
    }

    private function resolveActor(): string
    {
        $user = $this->security->getUser();
        if (null === $user) {
            throw new BadRequestHttpException('Utilisateur non authentifié.');
        }

        return $user->getUserIdentifier();
    }

    private function resolvePlan(?string $planId): SubscriptionPlan
    {
        if (null === $planId || '' === (string) $planId) {
            throw new BadRequestHttpException('Le plan est obligatoire.');
        }

        $plan = $this->plans->find((string) $planId);
        if (!$plan instanceof SubscriptionPlan) {
            throw new NotFoundHttpException('Plan introuvable.');
        }

        return $plan;
    }

    /**
     * @param list<string|int> $extensionIds
     *
     * @return list<Extension>
     */
    private function resolveExtensions(array $extensionIds): array
    {
        $granted = [];
        foreach ($extensionIds as $extensionId) {
            $extension = $this->extensions->find((string) $extensionId);
            if (!$extension instanceof Extension) {
                throw new NotFoundHttpException('Extension introuvable.');
            }
            $granted[(string) $extension->getId()] = $extension;
        }

        return array_values($granted);
    }

    private function createGrant(AccountSubscription $subscription, Extension $extension, string $actor): AccountSubscriptionExtension
    {
        $now = new \DateTimeImmutable();
        $expiresAt = null !== $extension->getDurationMonths()
            ? $now->modify(sprintf('+%d months', $extension->getDurationMonths()))
            : null;

        return new AccountSubscriptionExtension(
            accountSubscription: $subscription,
            extension: $extension,
            activatedAt: $now,
            expiresAt: $expiresAt,
            activatedBy: $actor,
        );
    }
}
