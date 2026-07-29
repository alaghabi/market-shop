<?php

namespace App\EventSubscriber;

use App\Enum\BoutiqueStatus;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use App\Service\Boutique\SubdomainResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class BoutiqueRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SubdomainResolver $resolver,
        private TokenStorageInterface $tokenStorage,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $boutiqueContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after the firewall so admin requests can be distinguished from public requests.
            KernelEvents::REQUEST => ['resolveBoutique', 0],
        ];
    }

    public function resolveBoutique(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only resolve for API routes
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Skip admin routes
        if (str_starts_with($path, '/api/admin')) {
            return;
        }

        // Skip health check
        if ('/api/health' === $path) {
            return;
        }

        $boutique = $this->resolver->resolveFromRequest($request);
        if (null === $boutique) {
            $identifier = $request->query->get('boutiqueSlug') ?? $request->query->get('boutiqueId');
            if (is_string($identifier) && '' !== $identifier) {
                $boutique = $this->boutiques->findBySlugOrId($identifier);
            }
        }
        if (null === $boutique) {
            return;
        }

        $request->attributes->set('_boutique', $boutique);
        $request->attributes->set('_boutique_id', $boutique->getId());

        $isStaff = $this->isAuthenticatedStaff();
        if ($isStaff && !$this->boutiqueContext->canAccessBoutique($boutique)) {
            throw new AccessDeniedHttpException('Accès à cette boutique refusé.');
        }
        $status = $boutique->getStatus();

        // PENDING boutiques: only accessible by admins
        if (BoutiqueStatus::Pending === $status && !$isStaff) {
            throw new AccessDeniedHttpException('Cette boutique n\'est pas encore publiée.');
        }

        // SUSPENDED boutiques: still accessible by admins for management
        if (BoutiqueStatus::Suspended === $status && !$isStaff) {
            throw new AccessDeniedHttpException('Cette boutique est suspendue.');
        }

        // REJECTED boutiques: not accessible publicly
        if (BoutiqueStatus::Rejected === $status && !$isStaff) {
            throw new NotFoundHttpException('Page non trouvée');
        }

        // ARCHIVED boutiques: not accessible
        if (BoutiqueStatus::Archived === $status && !$isStaff) {
            throw new NotFoundHttpException('Page non trouvée');
        }

        if (!$isStaff && !$boutique->isVisiblePublicly()) {
            throw new NotFoundHttpException('Page non trouvée');
        }
    }

    private function isAuthenticatedStaff(): bool
    {
        if (null === $this->tokenStorage->getToken()) {
            return false;
        }

        return $this->boutiqueContext->isStaff();
    }
}
