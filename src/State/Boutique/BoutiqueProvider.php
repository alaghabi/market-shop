<?php

namespace App\State\Boutique;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Boutique\BoutiqueOutput;
use App\Entity\Customer;
use App\Entity\Order;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use App\Service\Module\ModuleAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class BoutiqueProvider implements ProviderInterface
{
    public function __construct(
        private readonly BoutiqueRepository $repository,
        private readonly BoutiqueContext $context,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ModuleAccessService $moduleAccess,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array<BoutiqueOutput>|BoutiqueOutput|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|BoutiqueOutput|null
    {
        $identifier = $uriVariables['id'] ?? $uriVariables['slug'] ?? null;
        if (null !== $identifier) {
            $entity = $this->repository->findBySlugOrId($identifier);
            if (!$entity) {
                return null;
            }

            $isAdmin = $this->authorizationChecker->isGranted('ROLE_BOUTIQUE_ADMIN');

            if ($isAdmin) {
                if (!$this->context->canAccessBoutique($entity)) {
                    return null;
                }
            } elseif (!$entity->isVisiblePublicly()) {
                return null;
            }

            return $this->toOutput($entity);
        }

        if ($this->authorizationChecker->isGranted('ROLE_BOUTIQUE_ADMIN')) {
            $entities = $this->repository->findVisibleTo($this->context->getBoutiqueIds(), $this->context->isSuperAdmin());
        } else {
            $entities = $this->repository->findPublishedForPublic();
        }

        return array_map([$this, 'toOutput'], $entities);
    }

    private function toOutput(object $entity): BoutiqueOutput
    {
        $output = new BoutiqueOutput();
        $output->id = (string) $entity->getId();
        $output->name = $entity->getName();
        $output->slug = $entity->getSlug();
        $output->status = $entity->getStatus()->value;
        $output->ownerId = $entity->getOwner() ? (string) $entity->getOwner()->getId() : null;
        $output->description = $entity->getDescription();
        $output->coverImage = $entity->getCoverImage();
        $output->email = $entity->getEmail();
        $output->phone = $entity->getPhone();
        $output->website = $entity->getWebsite();
        $output->customDomain = $entity->getCustomDomain();
        $output->isVerified = $entity->isVerified();
        $output->isFeatured = $entity->isFeatured();
        $output->isPublished = $entity->isPublished();
        $output->approvedAt = $entity->getApprovedAt()?->format('c');
        $output->approvedBy = $entity->getApprovedBy();
        $output->rejectionReason = $entity->getRejectionReason();
        $output->createdAt = $entity->getCreatedAt();
        $output->updatedAt = $entity->getUpdatedAt();
        $output->usersCount = $entity->getUsers()->count();
        $output->productsCount = $entity->getProducts()->count();
        $output->ordersCount = $entity->getOrdersCount();
        $output->totalRevenue = $entity->getTotalRevenue();
        $output->hasActiveSubscription = $entity->hasActiveSubscription();
        $output->isVisiblePublicly = $entity->isVisiblePublicly();
        $output->reviewsEnabled = $this->moduleAccess->isModuleEnabled('reviews', $entity);
        $output->wishlistEnabled = $this->moduleAccess->isModuleEnabled('wishlist', $entity);
        $output->analyticsEnabled = $this->moduleAccess->isModuleEnabled('analytics', $entity);
        $output->viewsEnabled = $output->analyticsEnabled;
        $moduleConfig = $entity->getSettings()?->getModuleConfig() ?? [];
        $output->customerAccountsEnabled = $this->moduleAccess->isModuleEnabled('customer_auth', $entity)
            && (!array_key_exists('enable_customer_auth', $moduleConfig) || true === (bool) $moduleConfig['enable_customer_auth']);

        if ($output->analyticsEnabled) {
            $output->customersWithAccount = $this->countCustomers($entity, true);
            $output->customersWithoutAccount = $this->countCustomers($entity, false);
            $output->publicOrdersCount = (int) $this->em->getRepository(Order::class)->count(['boutique' => $entity]);
        }

        $settings = $entity->getSettings();
        if (null !== $settings) {
            $output->logoUrl = $settings->getLogoUrl();
            $output->primaryColor = $settings->getPrimaryColor() ?? '#3525cd';
            $output->secondaryColor = $settings->getSecondaryColor() ?? '#505f76';
            $output->domain = $settings->getDomain();
            $output->contactEmail = $settings->getContactEmail();
            $output->contactPhone = $settings->getContactPhone();
            $output->address = $settings->getAddress();
            $output->socialLinks = $settings->getSocialLinks();
            $output->colorPalette = $settings->getColorPalette();
            $output->theme = $settings->getTheme();
            $output->fontFamily = $settings->getFontFamily();
            $output->fontSize = $settings->getFontSize();
            $output->borderRadius = $settings->getBorderRadius();
            $output->iconSet = $settings->getIconSet();
            $output->headerConfig = $settings->getHeaderConfig();
            $output->footerConfig = $settings->getFooterConfig();
            $output->navigationItems = $settings->getNavigationItems();
            $output->frontOfficePages = $settings->getFrontOfficePages();
            $output->featuredCategories = $settings->getFeaturedCategories();
            $output->homepageSections = $settings->getHomepageSections();
            $output->banners = $settings->getBanners();
            $output->catalogConfig = $settings->getCatalogConfig();
            $output->moduleConfig = $moduleConfig;
            $output->slogan = $settings->getSlogan();
            $output->favicon = $settings->getFavicon();
            $output->maintenanceMessage = $settings->getMaintenanceMessage();
            $output->orderMode = $settings->getOrderMode()->value;
            $output->description = $settings->getDescription() ?? $output->description;
            $output->coverImage = $settings->getCoverImage() ?? $output->coverImage;
        } else {
            $output->primaryColor = '#3525cd';
            $output->secondaryColor = '#505f76';
            $output->domain = null;
            $output->logoUrl = null;
            $output->contactEmail = null;
            $output->contactPhone = null;
            $output->address = null;
            $output->socialLinks = [];
        }

        return $output;
    }

    private function countCustomers(object $boutique, bool $withAccount): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(customer.id)')
            ->from(Customer::class, 'customer')
            ->andWhere('customer.boutique = :boutique')
            ->andWhere('customer.deletedAt IS NULL')
            ->setParameter('boutique', $boutique);

        $qb->andWhere($withAccount ? 'customer.user IS NOT NULL' : 'customer.user IS NULL');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
