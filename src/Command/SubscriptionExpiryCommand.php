<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\Subscription;
use App\Repository\BoutiqueExtensionRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\Subscription\SubscriptionQuotaReconciler;
use App\Service\Notification\BackofficeNotificationService;
use App\State\Subscription\SubscriptionExtensionReconciler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:subscription-expiry',
    description: 'Vérifie les abonnements qui expirent et crée des notifications.',
)]
final class SubscriptionExpiryCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRepository $repository,
        private readonly UserRepository $users,
        private readonly BoutiqueExtensionRepository $boutiqueExtensions,
        private readonly SubscriptionExtensionReconciler $extensionReconciler,
        private readonly SubscriptionQuotaReconciler $quotaReconciler,
        private readonly BackofficeNotificationService $backofficeNotifications,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0, 0);
        $notified = 0;

        foreach ([7, 3, 1] as $days) {
            $targetDate = $today->modify(sprintf('+%d days', $days));
            $subscriptions = $this->repository->findActiveExpiringBetween(
                $targetDate->setTime(0, 0, 0),
                $targetDate->setTime(23, 59, 59),
            );

            foreach ($subscriptions as $subscription) {
                $boutique = $subscription->getBoutique();
                $endDate = $subscription->getEndDate();
                if (!$endDate) {
                    continue;
                }

                $messageEnd = $endDate->format('d/m/Y');
                $planLabel = $subscription->getPlan()->label();
                $message = sprintf(
                    'L\'abonnement %s de la boutique "%s" expire le %s.',
                    $planLabel,
                    $boutique->getName(),
                    $messageEnd,
                );

                $this->createNotificationForBoutiqueAdmins($subscription, 'subscription_expiring', $message);
                $this->createNotificationForSuperAdmins($subscription, 'subscription_expiring', $message);
                ++$notified;
                $output->writeln(sprintf('  [%dj] %s — %s', $days, $boutique->getName(), $messageEnd));
            }
        }

        $expired = $this->repository->findActiveExpired();
        foreach ($expired as $subscription) {
            $boutique = $subscription->getBoutique();
            $endDate = $subscription->getEndDate();
            $messageEnd = $endDate?->format('d/m/Y') ?? '?';

            $message = sprintf(
                'L\'abonnement %s de la boutique "%s" a expiré le %s.',
                $subscription->getPlan()->label(),
                $boutique->getName(),
                $messageEnd,
            );

            $this->backofficeNotifications->notifyBoutiqueAdmins(
                $boutique,
                'subscription_expired',
                'Abonnement expiré',
                $message,
                'subscription.expired',
            );
            $this->createNotificationForSuperAdmins($subscription, 'subscription_expired', $message);
            $subscription->markAsExpired();
            $deactivated = $this->extensionReconciler->deactivateAllActiveGrants($boutique);
            $quotaDeactivated = $this->quotaReconciler->reconcileExpired($boutique);
            ++$notified;
            $output->writeln(sprintf('  [expiré] %s — %s (%d extension(s), %d produit(s)/catégorie(s) désactivée(s))', $boutique->getName(), $messageEnd, $deactivated, $quotaDeactivated));
        }

        $this->em->flush();

        if (0 === $notified) {
            $output->writeln('Aucun abonnement à notifier.');
        }

        $output->writeln(sprintf('Notified %d subscription(s).', $notified));

        return Command::SUCCESS;
    }

    private function createNotificationForBoutiqueAdmins(Subscription $subscription, string $type, string $message): void
    {
        $boutique = $subscription->getBoutique();
        $admins = $boutique->getUsers();

        foreach ($admins as $user) {
            $notification = new Notification(
                recipientIdentifier: $user->getUserIdentifier(),
                type: $type,
                title: 'Abonnement',
                message: $message,
                boutique: $boutique,
            );
            $this->em->persist($notification);
        }
    }

    private function createNotificationForSuperAdmins(Subscription $subscription, string $type, string $message): void
    {
        $superAdmins = $this->users->findByRole('ROLE_SUPER_ADMIN');

        foreach ($superAdmins as $user) {
            $notification = new Notification(
                recipientIdentifier: $user->getUserIdentifier(),
                type: $type,
                title: 'Abonnement',
                message: $message,
                boutique: $subscription->getBoutique(),
            );
            $this->em->persist($notification);
        }
    }
}
