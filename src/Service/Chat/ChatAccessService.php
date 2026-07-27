<?php

namespace App\Service\Chat;

use App\Entity\Conversation;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ChatAccessService
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function canAccessConversation(Conversation $conversation, ?string $guestToken = null): bool
    {
        $user = $this->getCurrentUser();

        if ($user instanceof UserInterface) {
            if ($this->hasRole($user, 'ROLE_SUPER_ADMIN')) {
                return true;
            }

            if ($user instanceof \App\Entity\User && $this->hasRole($user, 'ROLE_BOUTIQUE_ADMIN') && $user->getAdministeredBoutiques()->contains($conversation->getBoutique())) {
                return true;
            }

            if ($user instanceof \App\Entity\User && (string) $conversation->getUser()?->getId() === (string) $user->getId()) {
                return true;
            }
        }

        return $conversation->isGuestAccessTokenValid($guestToken);
    }

    public function isAdminResponder(): bool
    {
        $user = $this->getCurrentUser();

        return $user instanceof UserInterface && ($this->hasRole($user, 'ROLE_SUPER_ADMIN') || $this->hasRole($user, 'ROLE_BOUTIQUE_ADMIN'));
    }

    public function canManageAllConversations(): bool
    {
        $user = $this->getCurrentUser();

        return $user instanceof UserInterface && $this->hasRole($user, 'ROLE_SUPER_ADMIN');
    }

    public function getAdministeredBoutiques(): array
    {
        $user = $this->getCurrentUser();

        if (!$user instanceof \App\Entity\User || (!$this->hasRole($user, 'ROLE_SUPER_ADMIN') && !$this->hasRole($user, 'ROLE_BOUTIQUE_ADMIN'))) {
            return [];
        }

        return $user->getAdministeredBoutiques()->toArray();
    }

    public function getCurrentUser(): mixed
    {
        return $this->tokenStorage->getToken()?->getUser();
    }

    private function hasRole(UserInterface $user, string $role): bool
    {
        return in_array($role, $user->getRoles(), true);
    }
}
