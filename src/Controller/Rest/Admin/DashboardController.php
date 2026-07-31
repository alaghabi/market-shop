<?php

namespace App\Controller\Rest\Admin;

use App\Repository\BoutiqueRepository;
use App\Repository\UserRepository;
use App\Security\BoutiqueContext;
use App\Security\Permission\PermissionAccessService;
use App\Service\Dashboard\DashboardService;
use App\Service\Module\ModuleAccessService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private DashboardService $dashboard,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private ParameterBagInterface $parameterBag,
        private ModuleAccessService $moduleAccess,
        private PermissionAccessService $permissionAccess,
        private Security $security,
        private UserRepository $users,
    ) {
    }

    #[Route('/api/admin/dashboard/modules', name: 'api_admin_dashboard_modules', methods: ['GET'])]
    public function modules(): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && !$this->isGranted('ROLE_BOUTIQUE_ADMIN') && !$this->isGranted('ROLE_CAISSIER')) {
            throw new AccessDeniedHttpException('Access denied');
        }

        $modules = $this->parameterBag->has('modules') ? $this->parameterBag->get('modules') : [];

        return new JsonResponse([
            'modules' => is_array($modules) ? $modules : [],
        ]);
    }

    #[Route('/api/admin/dashboard/platform', name: 'api_admin_dashboard_platform', methods: ['GET'])]
    public function platform(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return new JsonResponse($this->dashboard->platformOverview());
    }

    #[Route('/api/admin/boutiques/{boutiqueId}/dashboard', name: 'api_admin_dashboard_boutique', methods: ['GET'])]
    public function boutique(string $boutiqueId): JsonResponse
    {
        $boutique = $this->boutiques->findBySlugOrId($boutiqueId);
        if (!$boutique) {
            throw new NotFoundHttpException('Boutique not found');
        }
        if (!$this->context->canAccessBoutique($boutique)) {
            throw new AccessDeniedHttpException('Access denied');
        }

        return new JsonResponse($this->dashboard->boutiqueOverview((string) $boutique->getId()));
    }

    #[Route('/api/admin/boutiques/{boutiqueId}/dashboard/access', name: 'api_admin_dashboard_boutique_access', methods: ['GET'])]
    public function boutiqueAccess(string $boutiqueId): JsonResponse
    {
        $boutique = $this->boutiques->findBySlugOrId($boutiqueId);
        if (!$boutique) {
            throw new NotFoundHttpException('Boutique not found');
        }
        if (!$this->context->canAccessBoutique($boutique)) {
            throw new AccessDeniedHttpException('Access denied');
        }

        $modules = [];
        foreach ($this->moduleAccess->getAvailableModules($boutique) as $item) {
            $module = $item['module'];
            $modules[$module->getCode()] = [
                'code' => $module->getCode(),
                'name' => $module->getName(),
                'globallyEnabled' => $item['globallyEnabled'],
                'allowedBySubscription' => $item['allowedBySubscription'],
                'enabledInBoutique' => $item['enabledInBoutique'],
                'accessible' => $item['accessible'],
            ];
        }

        $roles = method_exists($this->security->getUser(), 'getRoles') ? $this->security->getUser()->getRoles() : [];
        $permissions = $this->permissionAccess->getPermissions($boutique);
        $tokenUser = $this->security->getUser();
        $userId = null;
        if ($tokenUser instanceof \App\Entity\User) {
            $userId = (string) $tokenUser->getId();
        } elseif (null !== $tokenUser) {
            $resolved = $this->users->findOneBy(['identifier' => $tokenUser->getUserIdentifier()]);
            $userId = null !== $resolved ? (string) $resolved->getId() : null;
        }

        return new JsonResponse([
            'modules' => $modules,
            'permissions' => $permissions,
            'roles' => $roles,
            'userId' => $userId,
        ]);
    }
}
