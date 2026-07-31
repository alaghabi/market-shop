<?php

namespace App\State\AccountSubscription;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AccountSubscription\AccountSubscriptionInput;
use App\Dto\AccountSubscription\AccountSubscriptionOutput;
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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<AccountSubscriptionInput|null, AccountSubscriptionOutput> */
final readonly class AccountSubscriptionProcessor implements ProcessorInterface
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
        $user = $this->resolveUser();
        if (!$user instanceof User) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }

        $subscription = match ($operation->getName()) {
            'account_subscription_renew' => $this->renew($user),
            default => $this->subscribe($user, $data),
        };

        $this->em->flush();
        $this->service->invalidate($user);

        $output = $this->provider->provide($operation, [], ['request' => null]);
        $output->id = (string) $subscription->getId();

        return $output;
    }

    private function subscribe(User $user, mixed $data): AccountSubscription
    {
        if (!$data instanceof AccountSubscriptionInput) {
            throw new BadRequestHttpException('Corps de requête invalide.');
        }

        $plan = $this->resolvePlan($data->planId);
        $current = $this->service->getActiveSubscription($user);
        $now = new \DateTimeImmutable();

        if (null !== $current && (string) $current->getSubscriptionPlan()?->getId() === (string) $plan->getId()) {
            return $this->renewSubscription($user, $current, $plan, $now);
        }

        $grantedExtensions = $this->resolveExtensions($data->extensionIds);
        $this->assertDowngradeAllowed($user, $plan, $grantedExtensions);

        $baseDate = $now;
        if (null !== $current && null !== $current->getEndDate() && $current->getEndDate() > $now) {
            $baseDate = $current->getEndDate();
        }

        $entity = new AccountSubscription($user, $plan);
        $entity->activateFrom($baseDate, $user->getUserIdentifier());
        $this->em->persist($entity);

        foreach ($grantedExtensions as $extension) {
            $this->em->persist($this->createGrant($entity, $extension, $user));
        }

        if (null !== $current) {
            $current->markAsReplaced($entity);
        }

        $this->auditLog->log(
            actorEmail: $user->getUserIdentifier(),
            actorRole: 'ROLE_BOUTIQUE_ADMIN',
            action: 'account_subscription.changed',
            resourceType: 'AccountSubscription',
            resourceId: (string) $entity->getId(),
            details: ['planId' => (string) $plan->getId(), 'planName' => $plan->getName()],
        );

        return $entity;
    }

    private function renew(User $user): AccountSubscription
    {
        $current = $this->service->getActiveSubscription($user);
        if (null === $current) {
            throw new BadRequestHttpException('Aucun abonnement actif à renouveler.');
        }

        $plan = $current->getSubscriptionPlan();
        if (null === $plan) {
            throw new BadRequestHttpException('Aucun plan associé à l\'abonnement.');
        }

        return $this->renewSubscription($user, $current, $plan, new \DateTimeImmutable());
    }

    private function renewSubscription(User $user, AccountSubscription $current, SubscriptionPlan $plan, \DateTimeImmutable $now): AccountSubscription
    {
        $baseDate = null !== $current->getEndDate() && $current->getEndDate() > $now
            ? $current->getEndDate()
            : $now;

        $current->activateFrom($baseDate, $user->getUserIdentifier());

        foreach ($current->getExtensions() as $grant) {
            $extension = $grant->getExtension();
            if (null === $extension->getDurationMonths()) {
                $grant->reactivate(null);
                continue;
            }
            $grantBase = null !== $grant->getExpiresAt() && $grant->getExpiresAt() > $now
                ? $grant->getExpiresAt()
                : $now;
            $grant->reactivate($grantBase->modify(sprintf('+%d months', $extension->getDurationMonths())));
        }

        $this->auditLog->log(
            actorEmail: $user->getUserIdentifier(),
            actorRole: 'ROLE_BOUTIQUE_ADMIN',
            action: 'account_subscription.renewed',
            resourceType: 'AccountSubscription',
            resourceId: (string) $current->getId(),
            details: ['planId' => (string) $plan->getId(), 'planName' => $plan->getName()],
        );

        return $current;
    }

    private function resolvePlan(?string $planId): SubscriptionPlan
    {
        if (null === $planId || '' === (string) $planId) {
            throw new BadRequestHttpException('Le plan est obligatoire.');
        }

        $plan = $this->plans->find((string) $planId);
        if (!$plan instanceof SubscriptionPlan || !$plan->isActive() || !$plan->isVisible()) {
            throw new NotFoundHttpException('Plan introuvable ou indisponible.');
        }

        return $plan;
    }

    /** @param list<Extension> $extensions */
    private function assertDowngradeAllowed(User $user, SubscriptionPlan $plan, array $extensions): void
    {
        $newMax = $this->service->getMaxBoutiquesForPlanAndExtensions($plan, $extensions);
        if (null === $newMax) {
            return;
        }

        $published = $this->service->getPublishedBoutiqueCount($user);
        if ($published > $newMax) {
            throw new ConflictHttpException(sprintf('Impossible de passer à ce plan : vous avez %d boutique(s) publiée(s), le nouveau plafond est de %d. Dépubliez des boutiques avant de changer de plan.', $published, $newMax));
        }
    }

    /** @param list<string|int> $extensionIds
     * @return list<Extension>
     */
    private function resolveExtensions(array $extensionIds): array
    {
        $granted = [];
        foreach ($extensionIds as $extensionId) {
            $extension = $this->extensions->find((string) $extensionId);
            if (!$extension instanceof Extension || !$extension->isActive()) {
                throw new NotFoundHttpException('Extension introuvable ou indisponible.');
            }
            if ($extension->requiresValidation()) {
                throw new BadRequestHttpException(sprintf('L\'extension "%s" nécessite une validation manuelle et ne peut pas être activée automatiquement.', $extension->getName()));
            }
            $granted[(string) $extension->getId()] = $extension;
        }

        return array_values($granted);
    }

    private function createGrant(AccountSubscription $subscription, Extension $extension, User $user): AccountSubscriptionExtension
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
            activatedBy: $user->getUserIdentifier(),
        );
    }

    private function resolveUser(): ?User
    {
        $tokenUser = $this->security->getUser();
        if ($tokenUser instanceof User) {
            return $tokenUser;
        }

        if (null === $tokenUser) {
            return null;
        }

        return $this->users->findOneBy(['identifier' => $tokenUser->getUserIdentifier()]);
    }
}
