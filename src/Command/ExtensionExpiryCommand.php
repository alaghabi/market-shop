<?php

namespace App\Command;

use App\Entity\Boutique;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\AccountSubscriptionExtensionRepository;
use App\Repository\BoutiqueExtensionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:extension-expiry',
    description: 'Verifie les extensions (module/theme/quota) qui expirent et desactive celles qui sont echues.',
)]
final class ExtensionExpiryCommand extends Command
{
    public function __construct(
        private readonly BoutiqueExtensionRepository $repository,
        private readonly AccountSubscriptionExtensionRepository $accountExtensions,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $notified = 0;

        $notified += $this->expireBoutiqueExtensions($now, $output);
        $notified += $this->expireAccountExtensions($now, $output);

        $this->em->flush();

        if (0 === $notified) {
            $output->writeln('Aucune extension a notifier.');
        }

        $output->writeln(sprintf('Notified %d extension grant(s).', $notified));

        return Command::SUCCESS;
    }

    private function expireBoutiqueExtensions(\DateTimeImmutable $now, OutputInterface $output): int
    {
        $notified = 0;

        $expiringSoon = $this->repository->findExpiringSoon($now, $now->modify('+3 days'));
        foreach ($expiringSoon as $grant) {
            $boutique = $grant->getBoutique();
            $message = sprintf(
                'L\'extension "%s" de la boutique "%s" expire le %s.',
                $grant->getExtension()->getName(),
                $boutique->getName(),
                $grant->getExpiresAt()?->format('d/m/Y') ?? '?',
            );

            $this->notifyBoutiqueAdmins($boutique, 'extension_expiring', $message);
            $this->notifySuperAdmins('extension_expiring', $message, $boutique);
            $grant->markExpiryNotified();
            ++$notified;
            $output->writeln(sprintf('  [bientot expire] %s — %s', $boutique->getName(), $grant->getExtension()->getName()));
        }

        $expired = $this->repository->findExpiredButStillActive($now);
        foreach ($expired as $grant) {
            $boutique = $grant->getBoutique();
            $message = sprintf(
                'L\'extension "%s" de la boutique "%s" a expire et a ete desactivee.',
                $grant->getExtension()->getName(),
                $boutique->getName(),
            );

            $grant->deactivate();
            $this->notifyBoutiqueAdmins($boutique, 'extension_expired', $message);
            $this->notifySuperAdmins('extension_expired', $message, $boutique);
            ++$notified;
            $output->writeln(sprintf('  [expire] %s — %s', $boutique->getName(), $grant->getExtension()->getName()));
        }

        return $notified;
    }

    private function expireAccountExtensions(\DateTimeImmutable $now, OutputInterface $output): int
    {
        $notified = 0;

        $expiringSoon = $this->accountExtensions->findExpiringSoon($now, $now->modify('+3 days'));
        foreach ($expiringSoon as $grant) {
            $user = $grant->getAccountSubscription()->getUser();
            $message = sprintf(
                'L\'extension "%s" de votre abonnement expire le %s.',
                $grant->getExtension()->getName(),
                $grant->getExpiresAt()?->format('d/m/Y') ?? '?',
            );

            $this->notifyUser($user, 'extension_expiring', $message);
            $grant->markExpiryNotified();
            ++$notified;
            $output->writeln(sprintf('  [bientot expire] abonnement %s — %s', $user->getUserIdentifier(), $grant->getExtension()->getName()));
        }

        $expired = $this->accountExtensions->findExpiredButStillActive($now);
        foreach ($expired as $grant) {
            $user = $grant->getAccountSubscription()->getUser();
            $message = sprintf(
                'L\'extension "%s" de votre abonnement a expire et a ete desactivee.',
                $grant->getExtension()->getName(),
            );

            $grant->deactivate();
            $this->notifyUser($user, 'extension_expired', $message);
            ++$notified;
            $output->writeln(sprintf('  [expire] abonnement %s — %s', $user->getUserIdentifier(), $grant->getExtension()->getName()));
        }

        return $notified;
    }

    private function notifyBoutiqueAdmins(Boutique $boutique, string $type, string $message): void
    {
        foreach ($boutique->getUsers() as $user) {
            $this->notifyUser($user, $type, $message, $boutique);
        }
    }

    private function notifySuperAdmins(string $type, string $message, ?Boutique $boutique = null): void
    {
        foreach ($this->users->findByRole('ROLE_SUPER_ADMIN') as $user) {
            $this->notifyUser($user, $type, $message, $boutique);
        }
    }

    private function notifyUser(User $user, string $type, string $message, ?Boutique $boutique = null): void
    {
        $this->em->persist(new Notification(
            recipientIdentifier: $user->getUserIdentifier(),
            type: $type,
            title: 'Extension',
            message: $message,
            boutique: $boutique,
        ));
    }
}
