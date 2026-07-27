<?php

namespace App\Service\Notification;

use App\Entity\Boutique;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Repository\UserShopRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BackofficeNotificationService
{
    public function __construct(
        private UserShopRepository $userShops,
        private NotificationService $notifications,
        private EntityManagerInterface $em,
    ) {
    }

    public function notifyBoutiqueAdmins(
        Boutique $boutique,
        string $type,
        string $title,
        string $message,
        string $eventCode,
    ): void {
        $recipients = [];
        foreach ($this->userShops->findByRoleAndBoutique('ROLE_BOUTIQUE_ADMIN', (string) $boutique->getId()) as $userShop) {
            $user = $userShop->getUser();
            $identifier = $user->getUserIdentifier();
            if (isset($recipients[$identifier])) {
                continue;
            }

            $recipients[$identifier] = true;
            $this->notifications->notify($identifier, $type, $title, $message, $boutique);
        }

        $this->em->flush();
        foreach (array_keys($recipients) as $recipient) {
            $this->dispatchEmail($boutique, $eventCode, $recipient);
        }
    }

    public function notifyUser(
        User $user,
        Boutique $boutique,
        string $type,
        string $title,
        string $message,
        string $eventCode,
    ): void {
        $recipient = $user->getUserIdentifier();
        $this->notifications->notify($recipient, $type, $title, $message, $boutique);
        $this->em->flush();
        $this->dispatchEmail($boutique, $eventCode, $recipient);
    }

    private function dispatchEmail(Boutique $boutique, string $eventCode, string $recipient): void
    {
        if (false === filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $this->notifications->dispatchExternal(
            $boutique,
            $eventCode,
            NotificationChannel::Email,
            $recipient,
        );
    }
}
