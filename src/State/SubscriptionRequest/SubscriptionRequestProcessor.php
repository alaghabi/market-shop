<?php

namespace App\State\SubscriptionRequest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SubscriptionRequest\SubscriptionRequestInput;
use App\Dto\SubscriptionRequest\SubscriptionRequestOutput;
use App\Entity\Boutique;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\SubscriptionRequest;
use App\Enum\SubscriptionStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\SubscriptionRequestRepository;
use App\Repository\UserRepository;
use App\Security\BoutiqueContext;
use App\Service\Audit\AuditLogService;
use App\Service\Notification\BackofficeNotificationService;
use App\Service\NotificationService;
use App\Service\Subscription\SubscriptionQuotaReconciler;
use App\State\Common\BoutiqueWriteResolverTrait;
use App\State\Subscription\SubscriptionExtensionReconciler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SubscriptionRequestProcessor implements ProcessorInterface
{
    use BoutiqueWriteResolverTrait;

    public function __construct(
        private readonly SubscriptionRequestRepository $repository,
        private readonly SubscriptionPlanRepository $plans,
        private readonly SubscriptionRepository $subscriptions,
        private readonly BoutiqueRepository $boutiques,
        private readonly EntityManagerInterface $em,
        private readonly BoutiqueContext $context,
        private readonly NotificationService $notifications,
        private readonly BackofficeNotificationService $backofficeNotifications,
        private readonly AuditLogService $auditLog,
        private readonly UserRepository $users,
        private readonly SubscriptionExtensionReconciler $extensionReconciler,
        private readonly SubscriptionQuotaReconciler $quotaReconciler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SubscriptionRequestOutput
    {
        $operationName = $operation->getName() ?? '';

        if ('approve_subscription_request' === $operationName) {
            return $this->approve((string) ($uriVariables['id'] ?? ''));
        }

        if ('reject_subscription_request' === $operationName) {
            return $this->reject((string) ($uriVariables['id'] ?? ''));
        }

        if (!$data instanceof SubscriptionRequestInput) {
            throw new \InvalidArgumentException('Expected SubscriptionRequestInput');
        }

        $boutique = $this->resolveBoutiqueForWrite($data, $uriVariables, $context);

        $plan = $this->findPlan($data->subscriptionPlanId);

        $entity = new SubscriptionRequest($boutique, $plan);
        $this->em->persist($entity);
        $this->em->flush();

        $this->notifyBoutique($boutique, 'subscription_requested', 'Demande envoyée', sprintf('Votre demande pour le plan "%s" a été enregistrée.', $plan->getName()));
        $this->notifySuperAdmins('subscription_request_new', 'Nouvelle demande d\'abonnement', sprintf('%s a demandé le plan "%s".', $boutique->getName(), $plan->getName()));
        $this->audit($boutique, 'subscription_request.created', $entity);

        return $this->toOutput($entity);
    }

    private function approve(string $id): SubscriptionRequestOutput
    {
        $entity = $this->findEntity($id);
        $user = $this->context->getUserIdentifier();
        $entity->approve($user ?? 'super-admin');

        $plan = $entity->getSubscriptionPlan();
        $boutique = $entity->getBoutique();

        $currentSubscription = $boutique->getCurrentSubscription();

        $now = new \DateTimeImmutable();

        if ($currentSubscription && SubscriptionStatus::Active === $currentSubscription->getStatus()) {
            $currentEnd = $currentSubscription->getEndDate();
            $baseDate = $currentEnd && $currentEnd > $now ? $currentEnd : $now;
        } else {
            $baseDate = $now;
        }

        $newEndDate = $plan->getDurationMonths() > 0
            ? $baseDate->modify(sprintf('+%d months', $plan->getDurationMonths()))
            : null;

        $subscription = new Subscription(
            boutique: $boutique,
            plan: \App\Enum\PlanType::Free,
            status: SubscriptionStatus::Active,
            startDate: $currentSubscription && SubscriptionStatus::Active === $currentSubscription->getStatus()
                ? $currentSubscription->getStartDate()
                : $now,
            endDate: $newEndDate,
            acceptedBy: $user ?? 'super-admin',
            acceptedAt: $now,
        );
        $subscription->setSubscriptionPlan($plan);

        if ($currentSubscription && SubscriptionStatus::Active === $currentSubscription->getStatus()) {
            $currentSubscription->markAsExpired();
        }

        $boutique->setCurrentSubscription($subscription);

        $deactivated = $this->extensionReconciler->reconcileAfterPlanChange($boutique, $plan);
        $reactivated = $this->extensionReconciler->reactivateCompatibleGrants($boutique, $plan);

        $quotaDeactivated = $this->quotaReconciler->reconcile($boutique);

        $this->em->persist($subscription);
        $this->em->flush();

        $this->notifyBoutique($boutique, 'subscription_activated', 'Abonnement activé', sprintf('Votre abonnement "%s" est maintenant actif.', $plan->getName()), 'subscription.approved');
        $this->notifySuperAdmins('subscription_approved', 'Abonnement approuvé', sprintf('L\'abonnement "%s" a été activé pour %s.', $plan->getName(), $boutique->getName()));
        $this->audit($boutique, 'subscription_request.approved', $entity, [
            'planId' => (string) $plan->getId(),
            'deactivatedExtensions' => $deactivated,
            'reactivatedExtensions' => $reactivated,
            'quotaDeactivated' => $quotaDeactivated,
        ]);

        return $this->toOutput($entity);
    }

    private function reject(string $id): SubscriptionRequestOutput
    {
        $entity = $this->findEntity($id);
        $entity->reject();
        $this->em->flush();

        $boutique = $entity->getBoutique();
        $this->notifyBoutique($boutique, 'subscription_rejected', 'Demande refusée', sprintf('Votre demande pour le plan "%s" a été refusée.', $entity->getSubscriptionPlan()->getName()), 'subscription.rejected');
        $this->audit($boutique, 'subscription_request.rejected', $entity);

        return $this->toOutput($entity);
    }

    private function toOutput(SubscriptionRequest $entity): SubscriptionRequestOutput
    {
        $output = new SubscriptionRequestOutput();
        $output->id = (string) $entity->getId();
        $output->boutiqueId = (string) $entity->getBoutique()->getId();
        $output->boutiqueName = $entity->getBoutique()->getName();
        $output->subscriptionPlanId = (string) $entity->getSubscriptionPlan()->getId();
        $output->subscriptionPlanName = $entity->getSubscriptionPlan()->getName();
        $output->status = $entity->getStatus()->value;
        $output->requestedAt = $entity->getRequestedAt()->format('c');
        $output->approvedAt = $entity->getApprovedAt()?->format('c');
        $output->approvedBy = $entity->getApprovedBy();

        return $output;
    }

    private function findBoutique(string $id): Boutique
    {
        $entity = $this->boutiques->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Boutique not found');
        }

        return $entity;
    }

    private function findPlan(string $id): SubscriptionPlan
    {
        $entity = $this->plans->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Subscription plan not found');
        }

        return $entity;
    }

    private function findEntity(string $id): SubscriptionRequest
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Subscription request not found');
        }

        return $entity;
    }

    private function notifyBoutique(Boutique $boutique, string $type, string $title, string $message, ?string $eventCode = null): void
    {
        if (null !== $eventCode) {
            $this->backofficeNotifications->notifyBoutiqueAdmins($boutique, $type, $title, $message, $eventCode);

            return;
        }

        $this->notifications->notify(null, $type, $title, $message, $boutique);
    }

    private function notifySuperAdmins(string $type, string $title, string $message): void
    {
        foreach ($this->users->findByRole('ROLE_SUPER_ADMIN') as $admin) {
            $this->notifications->notify($admin->getUserIdentifier(), $type, $title, $message);
        }
    }

    /** @param array<string, mixed> $extra */
    private function audit(Boutique $boutique, string $action, SubscriptionRequest $entity, array $extra = []): void
    {
        $this->auditLog->log(
            actorEmail: $this->context->getUserIdentifier() ?? 'system',
            actorRole: $this->context->isSuperAdmin() ? 'ROLE_SUPER_ADMIN' : 'ROLE_BOUTIQUE_ADMIN',
            action: $action,
            resourceType: 'SubscriptionRequest',
            resourceId: (string) $entity->getId(),
            details: array_merge([
                'planId' => (string) $entity->getSubscriptionPlan()->getId(),
                'planName' => $entity->getSubscriptionPlan()->getName(),
                'status' => $entity->getStatus()->value,
            ], $extra),
            boutiqueId: (string) $boutique->getId(),
        );
    }
}
