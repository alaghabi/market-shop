<?php

namespace App\Service\Backoffice;

use App\Entity\Boutique;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class BackofficeScopeResolver
{
    public function __construct(
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
    ) {
    }

    /** Null means all boutiques for a super administrator. */
    public function resolve(?Request $request): ?Boutique
    {
        $boutiqueId = $request?->query->get('boutiqueId');
        if (is_string($boutiqueId) && '' !== trim($boutiqueId) && 'all' !== strtolower(trim($boutiqueId))) {
            $boutique = $this->boutiques->findBySlugOrId(trim($boutiqueId));
            if (!$boutique instanceof Boutique || !$this->context->canAccessBoutique($boutique)) {
                throw new AccessDeniedHttpException('Boutique access denied.');
            }

            return $boutique;
        }

        if ($this->context->isSuperAdmin()) {
            return null;
        }

        $boutiqueId = $this->context->getBoutiqueId();

        return null !== $boutiqueId ? $this->boutiques->find((string) $boutiqueId) : null;
    }

    /** @return array{page: int, itemsPerPage: int} */
    public function pagination(?Request $request): array
    {
        $page = max(1, (int) ($request?->query->get('page', 1) ?? 1));
        $itemsPerPage = max(1, min(100, (int) ($request?->query->get('itemsPerPage', 20) ?? 20)));

        return ['page' => $page, 'itemsPerPage' => $itemsPerPage];
    }
}
