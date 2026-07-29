<?php

namespace App\State\Boutique;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Boutique\BoutiqueOutput;
use App\Entity\Customer;
use App\Entity\Order;
use App\Repository\BoutiqueRepository;
use App\Repository\ChatbotConfigRepository;
use App\Repository\CmsPageRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\Service\Chat\ChatbotCapabilityService;
use App\Service\Module\ModuleAccessService;
use App\State\Common\BackofficePaginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class BoutiqueProvider implements ProviderInterface
{
    public function __construct(
        private readonly BoutiqueRepository $repository,
        private readonly BoutiqueContext $context,
        private readonly ModuleAccessService $moduleAccess,
        private readonly ChatbotConfigRepository $chatbotConfigs,
        private readonly ChatbotCapabilityService $chatbotCapability,
        private readonly CmsPageRepository $cmsPages,
        private readonly EntityManagerInterface $em,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|BoutiqueOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $identifier = $uriVariables['id'] ?? $uriVariables['slug'] ?? null;
        if (null !== $identifier) {
            $entity = $this->repository->findBySlugOrId($identifier);
            if (!$entity) {
                return null;
            }

            $isStaff = $this->context->isStaff();

            if ($isStaff) {
                if (!$this->context->canAccessBoutique($entity)) {
                    return null;
                }
            } elseif (!$entity->isVisiblePublicly()) {
                return null;
            }

            return $this->toOutput($entity);
        }

        if ($this->context->isStaff()) {
            $result = $this->repository->findVisibleToPaginated(
                $this->context->getBoutiqueIds(),
                $this->context->isSuperAdmin(),
                $pagination['page'],
                $pagination['itemsPerPage'],
            );
        } else {
            $result = $this->repository->findPublishedForPublicPaginated(
                $pagination['page'],
                $pagination['itemsPerPage'],
            );
        }

        return new BackofficePaginator(
            array_map([$this, 'toOutput'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
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
        $chatbotConfig = $this->chatbotConfigs->findOneByBoutique($entity);
        $output->chatbotEnabled = $this->chatbotCapability->isVisible($entity, $chatbotConfig);
        $output->chatbotMode = $chatbotConfig?->getMode()->value ?? 'MANUAL';

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

        $cmsPages = $this->cmsPages->findPublishedByBoutiqueAndHeader($entity);
        $output->frontOfficePages = array_values(array_merge(
            $output->frontOfficePages,
            array_map(
                static fn ($page, int $index): array => [
                    'slug' => $page->getSlug(),
                    'label' => $page->getTitle(),
                    'enabled' => true,
                    'position' => 1000 + $index,
                    'source' => 'cms',
                ],
                $cmsPages,
                array_keys($cmsPages),
            ),
        ));

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
