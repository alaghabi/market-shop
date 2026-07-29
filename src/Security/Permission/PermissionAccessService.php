<?php

namespace App\Security\Permission;

use App\Entity\Boutique;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\RolePermissionRepository;
use App\Repository\UserRepository;
use App\Repository\UserShopRepository;
use App\Security\BoutiqueContext;
use App\Service\Boutique\ShopContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves the permissions of the authenticated application user in one shop
 * context. This service deliberately does not depend on any voter or access
 * service, so permission checks cannot recurse through SuggestionAccessService.
 */
class PermissionAccessService
{
    public function __construct(
        private readonly Security $security,
        private readonly RolePermissionRepository $permissions,
        private readonly BoutiqueContext $boutiqueContext,
        private readonly ShopContext $shopContext,
        private readonly BoutiqueRepository $boutiques,
        private readonly UserRepository $users,
        private readonly UserShopRepository $userShops,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function isGranted(string $permission, ?Boutique $boutique = null): bool
    {
        $permission = trim($permission);
        if ('' === $permission) {
            return false;
        }

        $user = $this->resolveUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $roles = $this->resolveEffectiveRoles($user, $boutique);
        if ([] === $roles) {
            return false;
        }

        $grantedPermissions = $this->permissions->findPermissionsByRoles($roles);

        return in_array('*', $grantedPermissions, true)
            || in_array($permission, $grantedPermissions, true);
    }

    /**
     * @return list<string>
     */
    public function getPermissions(?Boutique $boutique = null): array
    {
        $user = $this->resolveUser();
        if (!$user instanceof User) {
            return [];
        }

        if ($this->isSuperAdmin($user)) {
            return ['*'];
        }

        $roles = $this->resolveEffectiveRoles($user, $boutique);
        if ([] === $roles) {
            return [];
        }

        return $this->permissions->findPermissionsByRoles($roles);
    }

    public function getEffectiveRole(?Boutique $boutique = null): ?string
    {
        $user = $this->resolveUser();
        if (!$user instanceof User) {
            return null;
        }

        if ($this->isSuperAdmin($user)) {
            return 'ROLE_SUPER_ADMIN';
        }

        return $this->resolveEffectiveRoles($user, $boutique)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function resolveEffectiveRoles(User $user, ?Boutique $explicitBoutique): array
    {
        if ($explicitBoutique instanceof Boutique && !$this->boutiqueContext->canAccessBoutique($explicitBoutique)) {
            return [];
        }

        $boutique = $this->resolveBoutique($explicitBoutique);
        if ($boutique instanceof Boutique) {
            if (!$this->boutiqueContext->canAccessBoutique($boutique)) {
                return [];
            }

            if (in_array('ROLE_BOUTIQUE_ADMIN', $user->getRoles(), true)
                && $this->administersBoutique($user, $boutique)) {
                return ['ROLE_BOUTIQUE_ADMIN'];
            }

            $userShop = $this->userShops->findOneByUserAndBoutique(
                (string) $user->getId(),
                (string) $boutique->getId(),
            );
            if (null === $userShop || UserStatus::Active !== $userShop->getStatus()) {
                return [];
            }

            return [$userShop->getRole()];
        }

        // Customers have global permissions for public, non-shop-specific flows.
        // Staff must always be scoped to an active UserShop to avoid permission
        // leakage when the request has no boutique context.
        $roles = array_values(array_filter(
            $user->getRoles(),
            static fn (mixed $role): bool => 'ROLE_CUSTOMER' === $role,
        ));

        return $this->hasStaffRole($user) ? [] : $roles;
    }

    private function resolveBoutique(?Boutique $explicitBoutique): ?Boutique
    {
        if ($explicitBoutique instanceof Boutique) {
            return $this->boutiqueContext->canAccessBoutique($explicitBoutique) ? $explicitBoutique : null;
        }

        $request = $this->requestStack->getCurrentRequest();
        $requestBoutique = $request?->attributes->get('_boutique');
        if ($requestBoutique instanceof Boutique) {
            return $this->boutiqueContext->canAccessBoutique($requestBoutique) ? $requestBoutique : null;
        }

        $shop = $this->shopContext->getCurrentShop();
        if ($shop instanceof Boutique) {
            return $this->boutiqueContext->canAccessBoutique($shop) ? $shop : null;
        }

        if (null === $request) {
            return null;
        }

        $selector = $request->query->get('boutiqueId')
            ?? $request->query->get('boutiqueSlug')
            ?? $request->attributes->get('boutiqueId')
            ?? $request->attributes->get('boutiqueSlug');
        if (is_string($selector) && '' !== trim($selector)) {
            $boutique = $this->boutiques->findBySlugOrId(trim($selector));
            if ($boutique instanceof Boutique && $this->boutiqueContext->canAccessBoutique($boutique)) {
                $request->attributes->set('_boutique', $boutique);

                return $boutique;
            }
        }

        $user = $this->resolveUser();
        if (null !== $user && in_array('ROLE_BOUTIQUE_ADMIN', $user->getRoles(), true)) {
            $administeredBoutiques = $user->getAdministeredBoutiques()->toArray();
            if (1 === count($administeredBoutiques) && $administeredBoutiques[0] instanceof Boutique
                && $this->boutiqueContext->canAccessBoutique($administeredBoutiques[0])) {
                $request->attributes->set('_boutique', $administeredBoutiques[0]);

                return $administeredBoutiques[0];
            }
        }

        return null;
    }

    private function resolveUser(): ?User
    {
        $authenticated = $this->security->getUser();
        if ($authenticated instanceof User) {
            return $authenticated;
        }

        if (!$authenticated instanceof UserInterface) {
            return null;
        }

        $identifier = $authenticated->getUserIdentifier();
        if ('' === trim($identifier)) {
            return null;
        }

        $user = $this->users->findOneBy(['identifier' => $identifier]);

        return $user instanceof User ? $user : null;
    }

    private function isSuperAdmin(User $user): bool
    {
        return $this->security->isGranted('ROLE_SUPER_ADMIN')
            || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }

    private function hasStaffRole(User $user): bool
    {
        return [] !== array_intersect(
            ['ROLE_SUPER_ADMIN', 'ROLE_BOUTIQUE_ADMIN', 'ROLE_CAISSIER', 'ROLE_EMPLOYEE'],
            $user->getRoles(),
        );
    }

    private function administersBoutique(User $user, Boutique $boutique): bool
    {
        foreach ($user->getAdministeredBoutiques() as $administeredBoutique) {
            if ((string) $administeredBoutique->getId() === (string) $boutique->getId()) {
                return true;
            }
        }

        return false;
    }
}
