<?php

namespace App\Tests\Security\Permission;

use App\Entity\Boutique;
use App\Entity\RolePermission;
use App\Entity\User;
use App\Entity\UserShop;
use App\Enum\UserStatus;
use App\Security\Permission\PermissionAccessService;
use App\Security\Voter\PermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class PermissionAccessServiceTest extends KernelTestCase
{
    /** @var list<object> */
    private array $created = [];

    private EntityManagerInterface $entityManager;

    private TokenStorageInterface $tokenStorage;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->tokenStorage = $container->get(TokenStorageInterface::class);
        $this->requestStack = $container->get(RequestStack::class);
    }

    public function testSuperAdminHasWildcardAccessWithoutUserShop(): void
    {
        [$user, $boutique] = $this->createUserContext(
            appRoles: ['ROLE_SUPER_ADMIN'],
            userShopRole: null,
            userShopStatus: null,
        );

        self::assertTrue($this->service()->isGranted('anything.at.all', $boutique));
        self::assertSame(['*'], $this->service()->getPermissions($boutique));
        self::assertSame('ROLE_SUPER_ADMIN', $this->service()->getEffectiveRole($boutique));
        self::assertSame($user->getUserIdentifier(), $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier());
    }

    public function testActiveUserShopUsesItsRoleAndExactPermission(): void
    {
        [, $boutique, $role] = $this->createUserContext(
            appRoles: ['ROLE_CAISSIER'],
            permissions: ['product.read'],
        );

        $service = $this->service();

        self::assertTrue($service->isGranted('product.read', $boutique));
        self::assertFalse($service->isGranted('product.delete', $boutique));
        self::assertSame($role, $service->getEffectiveRole($boutique));
        self::assertContains('product.read', $service->getPermissions($boutique));
    }

    public function testBoutiqueAdminOwnerDoesNotNeedUserShopMembership(): void
    {
        [, $boutique] = $this->createUserContext(
            appRoles: ['ROLE_BOUTIQUE_ADMIN'],
            userShopRole: null,
            userShopStatus: null,
            permissions: ['product.create'],
            permissionRole: 'ROLE_BOUTIQUE_ADMIN',
        );

        self::assertSame('ROLE_BOUTIQUE_ADMIN', $this->service()->getEffectiveRole($boutique));
        self::assertTrue($this->service()->isGranted('product.create', $boutique));
    }

    public function testInactiveUserShopCannotUseItsPermissions(): void
    {
        [, $boutique] = $this->createUserContext(
            appRoles: ['ROLE_CAISSIER'],
            userShopStatus: UserStatus::Suspended,
            permissions: ['product.read'],
        );

        self::assertFalse($this->service()->isGranted('product.read', $boutique));
        self::assertSame([], $this->service()->getPermissions($boutique));
    }

    public function testMissingUserShopCannotUseBoutiquePermissions(): void
    {
        [, $boutique] = $this->createUserContext(
            appRoles: ['ROLE_CAISSIER'],
            userShopRole: null,
            userShopStatus: null,
            permissions: ['product.read'],
        );

        self::assertFalse($this->service()->isGranted('product.read', $boutique));
    }

    public function testWildcardPermissionGrantsAnyPermission(): void
    {
        [, $boutique] = $this->createUserContext(
            appRoles: ['ROLE_CAISSIER'],
            permissions: ['*'],
        );

        self::assertTrue($this->service()->isGranted('new.permission', $boutique));
    }

    public function testStaffWithoutBoutiqueContextIsDenied(): void
    {
        $this->createUserContext(
            appRoles: ['ROLE_CAISSIER'],
            permissions: ['product.read'],
            setBoutiqueContext: false,
        );

        self::assertFalse($this->service()->isGranted('product.read'));
    }

    public function testAnonymousUserIsDenied(): void
    {
        $this->tokenStorage->setToken(null);

        self::assertFalse($this->service()->isGranted('product.read'));
        self::assertSame([], $this->service()->getPermissions());
        self::assertNull($this->service()->getEffectiveRole());
    }

    public function testVoterGrantsSupportedPermissionAndDeniesMalformedSubject(): void
    {
        [, $boutique] = $this->createUserContext(
            appRoles: ['ROLE_CAISSIER'],
            permissions: ['product.read'],
        );
        $token = $this->tokenStorage->getToken();
        self::assertNotNull($token);

        $voter = new PermissionVoter($this->service());

        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($token, 'product.read', ['PERMISSION']));
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($token, 'product.delete', ['PERMISSION']));
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($token, null, ['PERMISSION']));
        self::assertSame(Voter::ACCESS_ABSTAIN, $voter->vote($token, 'product.read', ['ROLE_USER']));
        self::assertTrue($this->service()->isGranted('product.read', $boutique));
    }

    /**
     * @param list<string> $appRoles
     * @param list<string> $permissions
     *
     * @return array{0: User, 1: Boutique, 2: ?string}
     */
    private function createUserContext(
        array $appRoles,
        ?string $userShopRole = 'ROLE_TEST_PERMISSION',
        ?UserStatus $userShopStatus = UserStatus::Active,
        array $permissions = [],
        bool $setBoutiqueContext = true,
        ?string $permissionRole = null,
    ): array {
        $suffix = bin2hex(random_bytes(6));
        $role = null !== $userShopRole ? 'ROLE_TP_'.$suffix : null;
        $boutique = new Boutique('Permission '.$suffix, 'permission-'.$suffix);
        $user = new User($boutique, 'permission-'.$suffix.'@example.test', $appRoles, status: UserStatus::Active);

        $this->entityManager->persist($boutique);
        $this->entityManager->persist($user);
        $this->created[] = $boutique;
        $this->created[] = $user;

        if (null !== $role && null !== $userShopStatus) {
            $userShop = new UserShop($user, $boutique, $role, $userShopStatus);
            $user->addUserShop($userShop);
            $this->entityManager->persist($userShop);
            $this->created[] = $userShop;
        }

        foreach ($permissions as $permission) {
            $permissionRoleCode = $permissionRole ?? $role ?? 'ROLE_UNUSED_'.$suffix;
            $repository = static::getContainer()->get(\App\Repository\RolePermissionRepository::class);
            if (null !== $repository->findOneBy(['roleCode' => $permissionRoleCode, 'permission' => $permission])) {
                continue;
            }

            $rolePermission = new RolePermission($permissionRoleCode, $permission);
            $this->entityManager->persist($rolePermission);
            $this->created[] = $rolePermission;
        }

        $this->entityManager->flush();

        $tokenUser = new InMemoryUser($user->getUserIdentifier(), null, $appRoles);
        $this->tokenStorage->setToken(new UsernamePasswordToken($tokenUser, 'main', $appRoles));

        $request = Request::create('http://localhost/api/admin/permission-test');
        if ($setBoutiqueContext) {
            $request->attributes->set('_boutique', $boutique);
        }
        $this->requestStack->push($request);

        return [$user, $boutique, $role];
    }

    private function service(): PermissionAccessService
    {
        return static::getContainer()->get(PermissionAccessService::class);
    }

    protected function tearDown(): void
    {
        $this->tokenStorage->setToken(null);
        while (null !== $this->requestStack->getCurrentRequest()) {
            $this->requestStack->pop();
        }

        if ([] !== $this->created) {
            foreach (array_reverse($this->created) as $entity) {
                if ($this->entityManager->contains($entity)) {
                    $this->entityManager->remove($entity);
                }
            }
            $this->entityManager->flush();
        }

        $this->created = [];
        self::ensureKernelShutdown();
        parent::tearDown();
    }
}
