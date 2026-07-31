<?php

namespace App\State\AccountSubscription;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\AccountSubscription\AccountSubscriptionChangePreviewOutput;
use App\Dto\AccountSubscription\AccountSubscriptionOutput;
use App\Entity\AccountSubscription;
use App\Entity\User;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\UserRepository;
use App\Service\Subscription\AccountSubscriptionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<AccountSubscriptionOutput|AccountSubscriptionChangePreviewOutput> */
final readonly class AccountSubscriptionProvider implements ProviderInterface
{
    public function __construct(
        private AccountSubscriptionService $service,
        private SubscriptionPlanRepository $plans,
        private UserRepository $users,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccountSubscriptionOutput|AccountSubscriptionChangePreviewOutput|null
    {
        $user = $this->resolveUser();
        if (!$user instanceof User) {
            return null;
        }

        if ('account_subscription_change_preview' === $operation->getName()) {
            return $this->previewChange($user, $context['request'] ?? null);
        }

        return $this->current($user);
    }

    public function current(User $user): AccountSubscriptionOutput
    {
        $subscription = $this->service->getActiveSubscription($user);
        $now = new \DateTimeImmutable();
        $output = new AccountSubscriptionOutput();

        $output->isActive = null !== $subscription;
        $output->publishedBoutiques = $this->service->getPublishedBoutiqueCount($user);
        $output->maxBoutiques = $this->service->getMaxBoutiques($user);
        $output->isUnlimited = null === $output->maxBoutiques;

        if (null === $subscription) {
            return $output;
        }

        $plan = $subscription->getSubscriptionPlan();
        $output->id = (string) $subscription->getId();
        $output->planId = null !== $plan ? (string) $plan->getId() : null;
        $output->planName = $plan?->getName();
        $output->planDescription = $plan?->getDescription();
        $output->planDurationMonths = $plan?->getDurationMonths() ?? 0;
        $output->planPriceTnd = $plan?->getPriceTnd() ?? 0;
        $output->planRenewalPriceTnd = $plan?->getRenewalPriceTnd();
        $output->effectivePlanRenewalPriceTnd = $this->service->getPlanRenewalBasePrice($plan);
        $output->extensionsRenewalPriceTnd = $this->service->getActiveExtensionsPrice($subscription);
        $output->currency = $plan?->getCurrency() ?? 'TND';
        $output->startDate = $subscription->getStartDate()?->format('c');
        $output->endDate = $subscription->getEndDate()?->format('c');
        $output->status = $subscription->getStatus()->value;
        $output->renewalPriceTnd = $this->service->getRenewalPrice($subscription);

        if (null !== $subscription->getEndDate()) {
            $output->daysRemaining = max(0, (int) $subscription->getEndDate()->diff($now)->days);
        }

        $output->extensions = array_map(
            static function ($grant): array {
                $extension = $grant->getExtension();

                return [
                    'id' => (string) $grant->getId(),
                    'code' => $extension->getCode(),
                    'name' => $extension->getName(),
                    'targetCode' => $extension->getTargetCode(),
                    'value' => $extension->getValue(),
                    'priceTnd' => $extension->getPriceTnd(),
                    'durationMonths' => $extension->getDurationMonths(),
                    'expiresAt' => $grant->getExpiresAt()?->format('c'),
                    'isActive' => $grant->isActive(),
                ];
            },
            $this->service->getActiveExtensions($subscription),
        );

        return $output;
    }

    private function previewChange(User $user, mixed $request): AccountSubscriptionChangePreviewOutput
    {
        $planId = $request instanceof Request ? $request->query->get('planId') : null;
        if (null === $planId || '' === (string) $planId) {
            throw new NotFoundHttpException('Paramètre planId manquant.');
        }

        $plan = $this->plans->find((string) $planId);
        if (null === $plan || !$plan->isActive() || !$plan->isVisible()) {
            throw new NotFoundHttpException('Plan introuvable ou indisponible.');
        }

        $subscription = $this->service->getActiveSubscription($user);
        $currentPlan = $subscription?->getSubscriptionPlan();

        $output = new AccountSubscriptionChangePreviewOutput();
        $output->currentPlanId = null !== $currentPlan ? (string) $currentPlan->getId() : null;
        $output->currentPlanName = $currentPlan?->getName();
        $output->newPlanId = (string) $plan->getId();
        $output->newPlanName = $plan->getName();
        $output->newPlanPriceTnd = $plan->getPriceTnd();
        $output->newPlanRenewalPriceTnd = $plan->getRenewalPriceTnd();
        $output->effectivePlanRenewalPriceTnd = $plan->getEffectiveRenewalPriceTnd();
        $output->currency = $plan->getCurrency();
        $output->durationMonths = $plan->getDurationMonths();
        $output->isRenewal = null !== $currentPlan && (string) $currentPlan->getId() === (string) $plan->getId();
        $output->publishedBoutiques = $this->service->getPublishedBoutiqueCount($user);

        $activeExtensions = null !== $subscription ? $this->service->getActiveExtensions($subscription) : [];
        $output->extensionsRenewalPriceTnd = array_sum(array_map(
            static fn ($grant): int => $grant->getExtension()->getPriceTnd(),
            $activeExtensions,
        ));
        $output->renewalPriceTnd = $this->service->estimateRenewalPrice($plan, $activeExtensions);
        $output->newMaxBoutiques = $this->service->getMaxBoutiquesForPlanAndExtensions(
            $plan,
            null !== $subscription ? $subscription->getExtensions() : [],
        );
        $output->newIsUnlimited = null === $output->newMaxBoutiques;
        $output->wouldExceedQuota = null !== $output->newMaxBoutiques
            && $output->publishedBoutiques > $output->newMaxBoutiques;

        $output->projectedEndDate = $this->projectEndDate($subscription, $plan);

        return $output;
    }

    private function projectEndDate(?AccountSubscription $subscription, \App\Entity\SubscriptionPlan $plan): ?string
    {
        if ($plan->getDurationMonths() <= 0) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $baseDate = $now;

        if (null !== $subscription && null !== $subscription->getEndDate() && $subscription->getEndDate() > $now) {
            $baseDate = $subscription->getEndDate();
        }

        return $baseDate->modify(sprintf('+%d months', $plan->getDurationMonths()))->format('c');
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
