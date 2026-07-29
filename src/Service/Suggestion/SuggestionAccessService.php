<?php

namespace App\Service\Suggestion;

use App\Entity\Boutique;
use App\Entity\Suggestion;
use App\Entity\User;
use App\Enum\SuggestionStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\RolePermissionRepository;
use App\Repository\UserRepository;
use App\Security\BoutiqueContext;
use App\Security\Permission\PermissionAccessService;
use App\Service\Boutique\ShopContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class SuggestionAccessService
{
    public function __construct(
        private Security $security,
        private RolePermissionRepository $permissions,
        private BoutiqueContext $boutiqueContext,
        private ShopContext $shopContext,
        private BoutiqueRepository $boutiques,
        private UserRepository $users,
        private ?PermissionAccessService $permissionAccess = null,
    ) {
    }

    public function requireUser(): User
    {
        $user = $this->resolveUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        return $user;
    }

    public function resolveBoutique(?Request $request = null, bool $required = true): ?Boutique
    {
        $request ??= null;
        $boutique = $request?->attributes->get('_boutique');
        if ($boutique instanceof Boutique && $this->canUseBoutique($boutique)) {
            return $boutique;
        }

        $boutique = $this->shopContext->getCurrentShop();
        if ($boutique instanceof Boutique && $this->canUseBoutique($boutique)) {
            return $boutique;
        }

        // Query/path values are context selectors only; they are never taken from a DTO
        // and are always checked against the authenticated user's boutique access.
        if (null !== $request && ($this->isSuperAdmin() || $this->isAuthenticated())) {
            $selector = $request->query->get('boutiqueId') ?? $request->query->get('boutiqueSlug') ?? $request->attributes->get('boutiqueId');
            if (is_string($selector) && '' !== $selector) {
                $boutique = $this->boutiques->findBySlugOrId($selector);
                if ($boutique instanceof Boutique && $this->canUseBoutique($boutique)) {
                    $request->attributes->set('_boutique', $boutique);

                    return $boutique;
                }
            }
        }

        $user = $this->resolveUser();
        if ($user instanceof User && $user->getBoutique() instanceof Boutique && $this->canUseBoutique($user->getBoutique())) {
            return $user->getBoutique();
        }

        $boutique = $this->boutiqueContext->getBoutiqueId();
        if (null !== $boutique) {
            $resolved = $this->boutiques->find((string) $boutique);
            if ($resolved instanceof Boutique && $this->canUseBoutique($resolved)) {
                return $resolved;
            }
        }

        if ($required) {
            throw new NotFoundHttpException('Boutique context not found.');
        }

        return null;
    }

    public function hasPermission(string $permission, ?Boutique $boutique = null): bool
    {
        if ($this->permissionAccess instanceof PermissionAccessService) {
            return $this->permissionAccess->isGranted($permission, $boutique);
        }

        $user = $this->resolveUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($boutique instanceof Boutique && !$this->boutiqueContext->canAccessBoutique($boutique)) {
            return false;
        }

        foreach ($user->getRoles() as $role) {
            if (null !== $this->permissions->findOneBy(['roleCode' => $role, 'permission' => '*'])) {
                return true;
            }
            if (null !== $this->permissions->findOneBy(['roleCode' => $role, 'permission' => $permission])) {
                return true;
            }
        }

        return false;
    }

    public function assertPermission(string $permission, ?Boutique $boutique = null): void
    {
        $this->requireUser();
        if (!$this->hasPermission($permission, $boutique)) {
            throw new AccessDeniedHttpException('You do not have permission to perform this action.');
        }
    }

    public function assertCanRead(Suggestion $suggestion, bool $public = false): void
    {
        if ($public) {
            if (!$this->isPubliclyVisible($suggestion)) {
                throw new NotFoundHttpException('Suggestion not found.');
            }

            return;
        }

        $user = $this->requireUser();
        if (!$this->canUseBoutique($suggestion->getBoutique())) {
            throw new NotFoundHttpException('Suggestion not found.');
        }
        $this->assertCurrentBoutique($suggestion->getBoutique());
        if ($suggestion->getCreatedBy() === $user) {
            return;
        }
        if ('private' === $suggestion->getVisibility()->value) {
            if (!$this->isAdmin($suggestion->getBoutique())) {
                throw new AccessDeniedHttpException('Suggestion is private.');
            }

            return;
        }
        if ('admins' === $suggestion->getVisibility()->value && !$this->hasPermission('suggestion.read', $suggestion->getBoutique())) {
            throw new AccessDeniedHttpException('Suggestion is restricted to administrators.');
        }
        if (!$this->hasPermission('suggestion.read', $suggestion->getBoutique())) {
            throw new AccessDeniedHttpException('You do not have permission to read this suggestion.');
        }
    }

    public function assertCanManage(Suggestion $suggestion, string $permission): User
    {
        $user = $this->requireUser();
        if (!$this->canUseBoutique($suggestion->getBoutique())) {
            throw new NotFoundHttpException('Suggestion not found.');
        }
        $this->assertCurrentBoutique($suggestion->getBoutique());
        $isOwner = $suggestion->getCreatedBy() === $user;
        $canModerate = $this->hasPermission('suggestion.moderate', $suggestion->getBoutique());
        if (!$this->hasPermission($permission, $suggestion->getBoutique()) && !$canModerate) {
            throw new AccessDeniedHttpException('You do not have permission to modify this suggestion.');
        }
        if (!$isOwner && !$canModerate) {
            throw new AccessDeniedHttpException('Only the author or a moderator can modify this suggestion.');
        }
        if ($isOwner && !$canModerate && in_array($permission, ['suggestion.update', 'suggestion.delete'], true)
            && !in_array($suggestion->getStatus(), [SuggestionStatus::DRAFT, SuggestionStatus::SUBMITTED], true)) {
            throw new AccessDeniedHttpException('This suggestion can no longer be modified.');
        }

        return $user;
    }

    public function assertCanInteract(Suggestion $suggestion, string $permission): User
    {
        $user = $this->requireUser();

        if ($this->isPubliclyVisible($suggestion)) {
            $this->assertPermission($permission);

            return $user;
        }

        $this->assertPermission($permission, $suggestion->getBoutique());
        $this->assertCanRead($suggestion);

        return $user;
    }

    public function isPubliclyVisible(Suggestion $suggestion): bool
    {
        return $suggestion->isPublished()
            && 'public' === $suggestion->getVisibility()->value
            && !in_array($suggestion->getStatus()->value, ['draft', 'archived', 'rejected'], true);
    }

    public function canUseBoutique(Boutique $boutique): bool
    {
        if ($this->isSuperAdmin() || $this->boutiqueContext->canAccessBoutique($boutique)) {
            return true;
        }

        $user = $this->resolveUser();
        if ($user instanceof User && $user->getBoutique() instanceof Boutique && (string) $user->getBoutique()->getId() === (string) $boutique->getId()) {
            return true;
        }

        return null === $user && $boutique->isVisiblePublicly();
    }

    public function isSuperAdmin(): bool
    {
        return $this->security->isGranted('ROLE_SUPER_ADMIN');
    }

    public function isAuthenticated(): bool
    {
        return $this->resolveUser() instanceof User;
    }

    public function isAdmin(Boutique $boutique): bool
    {
        return $this->hasPermission('suggestion.moderate', $boutique);
    }

    private function assertCurrentBoutique(Boutique $boutique): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }
        $current = $this->shopContext->getCurrentShop();
        if ($current instanceof Boutique && (string) $current->getId() !== (string) $boutique->getId()) {
            throw new NotFoundHttpException('Suggestion not found.');
        }
    }

    private function resolveUser(): ?User
    {
        $authenticated = $this->security->getUser();
        if ($authenticated instanceof User) {
            return $authenticated;
        }

        $identifier = $authenticated?->getUserIdentifier();

        return is_string($identifier) && '' !== $identifier
            ? $this->users->findOneBy(['identifier' => $identifier])
            : null;
    }
}
