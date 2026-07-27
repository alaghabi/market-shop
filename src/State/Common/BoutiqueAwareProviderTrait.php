<?php

namespace App\State\Common;

use App\Entity\Boutique;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

trait BoutiqueAwareProviderTrait
{
    private function resolveBoutiqueFromRequest(array $context, array $uriVariables = []): ?Boutique
    {
        $request = $context['request'] ?? null;
        if ($request instanceof Request) {
            $boutique = $request->attributes->get('_boutique');
            if (null !== $boutique) {
                return $boutique;
            }

            $boutiqueId = $request->query->get('boutiqueId');
            if (null !== $boutiqueId && isset($this->boutiques) && $this->boutiques instanceof BoutiqueRepository) {
                $boutique = $this->boutiques->findBySlugOrId((string) $boutiqueId);

                return $this->canUseResolvedBoutique($boutique, true) ? $boutique : null;
            }

            $boutiqueSlug = $request->query->get('boutiqueSlug');
            if (null !== $boutiqueSlug && isset($this->boutiques) && $this->boutiques instanceof BoutiqueRepository) {
                $boutique = $this->boutiques->findBySlug((string) $boutiqueSlug);
                if ($this->canUseResolvedBoutique($boutique, false)) {
                    return $boutique;
                }
            }
        }

        $boutiqueId = $uriVariables['boutiqueId'] ?? null;
        if (is_string($boutiqueId) && '' !== $boutiqueId && isset($this->boutiques) && $this->boutiques instanceof BoutiqueRepository) {
            $boutique = $this->boutiques->findBySlugOrId($boutiqueId);

            return $this->canUseResolvedBoutique($boutique, Uuid::isValid($boutiqueId)) ? $boutique : null;
        }

        // A super admin without an explicit selection means "all boutiques".
        // Only boutique-scoped users receive their default boutique here.
        if (isset($this->context) && $this->context instanceof BoutiqueContext && !$this->context->isSuperAdmin()) {
            $boutiqueId = $this->context->getBoutiqueId();
            if (null !== $boutiqueId && isset($this->boutiques) && $this->boutiques instanceof BoutiqueRepository) {
                return $this->boutiques->find((string) $boutiqueId);
            }
        }

        return null;
    }

    private function canUseResolvedBoutique(?Boutique $boutique, bool $requiresAuthenticatedAccess): bool
    {
        if (!$boutique instanceof Boutique) {
            return false;
        }

        if (isset($this->context) && $this->context instanceof BoutiqueContext && $this->context->canAccessBoutique($boutique)) {
            return true;
        }

        if ($requiresAuthenticatedAccess) {
            return false;
        }

        return $boutique->isVisiblePublicly();
    }
}
