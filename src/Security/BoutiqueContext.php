<?php

namespace App\Security;

use App\Entity\Boutique;
use App\Enum\UserStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

final readonly class BoutiqueContext
{
    public function __construct(
        private Security $security,
        private UserRepository $users,
        private BoutiqueRepository $boutiques,
    ) {
    }

    public function isSuperAdmin(): bool
    {
        return $this->security->isGranted('ROLE_SUPER_ADMIN');
    }

    public function isStaff(): bool
    {
        return $this->isSuperAdmin()
            || $this->security->isGranted('ROLE_BOUTIQUE_ADMIN')
            || $this->security->isGranted('ROLE_CAISSIER')
            || $this->security->isGranted('ROLE_EMPLOYEE');
    }

    public function getBoutiqueId(): ?Uuid
    {
        $boutiqueIds = $this->getBoutiqueIds();

        return $boutiqueIds[0] ?? null;
    }

    /** @return list<Uuid> */
    public function getBoutiqueIds(): array
    {
        if ($this->isSuperAdmin()) {
            return array_values(array_map(
                static fn (Boutique $boutique): Uuid => $boutique->getId(),
                $this->boutiques->findAll(),
            ));
        }

        $user = $this->security->getUser();
        if (null === $user) {
            return [];
        }

        $appUser = $this->users->findOneBy(['identifier' => $user->getUserIdentifier()]);

        if (null === $appUser) {
            return [];
        }

        $boutiques = $appUser->getAdministeredBoutiques()->toArray();
        foreach ($appUser->getUserShops() as $userShop) {
            if (UserStatus::Active === $userShop->getStatus()) {
                $boutiques[] = $userShop->getBoutique();
            }
        }

        $ids = [];
        foreach ($boutiques as $boutique) {
            $ids[(string) $boutique->getId()] = $boutique->getId();
        }

        return array_values($ids);
    }

    public function getUserIdentifier(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    public function canAccessBoutique(Boutique $boutique): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($this->getBoutiqueIds() as $boutiqueId) {
            if ((string) $boutiqueId === (string) $boutique->getId()) {
                return true;
            }
        }

        return false;
    }
}
