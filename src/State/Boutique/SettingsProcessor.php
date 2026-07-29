<?php

namespace App\State\Boutique;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Boutique\BoutiqueSettingsInput;
use App\Dto\Boutique\BoutiqueSettingsOutput;
use App\Entity\Boutique;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use App\Service\FrontOfficeCacheService;
use App\Service\Module\ModuleAccessService;
use App\Service\Subscription\SubscriptionManager;
use App\Service\Theme\ThemePresetRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<BoutiqueSettingsOutput> */
final readonly class SettingsProcessor implements ProcessorInterface
{
    public function __construct(
        private BoutiqueRepository $boutiques,
        private EntityManagerInterface $em,
        private BoutiqueContext $context,
        private FrontOfficeCacheService $cache,
        private SettingsProvider $provider,
        private ThemePresetRegistry $themePresets,
        private \App\Repository\ThemeRepository $themes,
        private ModuleAccessService $modules,
        private SubscriptionManager $subscriptionManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BoutiqueSettingsOutput
    {
        unset($operation);

        $boutique = $this->resolveBoutique($uriVariables, $context);

        $settings = $boutique->getSettings();
        if (!$settings) {
            $settings = new \App\Entity\BoutiqueSettings($boutique);
            $this->em->persist($settings);
        }

        assert($data instanceof BoutiqueSettingsInput);

        if (null !== $data->shopName && '' !== trim($data->shopName)) {
            $boutique->setName(trim($data->shopName));
        }

        $this->applyScalarFields($settings, $data, $boutique);
        $this->applyJsonFields($settings, $data);

        $this->em->flush();
        $this->cache->invalidateAll((string) $boutique->getId());

        return $this->provider->provide(
            new \ApiPlatform\Metadata\Get(),
            ['boutiqueId' => (string) $boutique->getId()],
        );
    }

    /** @param array<string, mixed> $uriVariables */
    private function resolveBoutique(array $uriVariables, array $context): Boutique
    {
        $request = $context['request'] ?? null;
        $identifier = $uriVariables['boutiqueId']
            ?? ($request instanceof Request ? $request->query->get('boutiqueId') : null)
            ?? ($request instanceof Request ? $request->query->get('boutiqueSlug') : null);

        if (is_string($identifier) && '' !== $identifier) {
            $boutique = $this->boutiques->findBySlugOrId($identifier);
            if (!$boutique) {
                throw new NotFoundHttpException('Boutique not found');
            }

            if (!$this->context->canAccessBoutique($boutique)) {
                throw new AccessDeniedHttpException('Access denied');
            }

            return $boutique;
        }

        $boutique = $request instanceof Request ? $request->attributes->get('_boutique') : null;
        if ($boutique instanceof Boutique) {
            if (!$this->context->canAccessBoutique($boutique)) {
                throw new AccessDeniedHttpException('Access denied');
            }

            return $boutique;
        }

        if ($this->context->isSuperAdmin()) {
            throw new BadRequestHttpException('Boutique required for super admin.');
        }

        $boutiqueId = $this->context->getBoutiqueId();
        $boutique = null !== $boutiqueId ? $this->boutiques->find((string) $boutiqueId) : null;
        if (!$boutique) {
            throw new NotFoundHttpException('Boutique not found');
        }

        if (!$this->context->canAccessBoutique($boutique)) {
            throw new AccessDeniedHttpException('Access denied');
        }

        return $boutique;
    }

    private function applyScalarFields(\App\Entity\BoutiqueSettings $s, BoutiqueSettingsInput $d, Boutique $boutique): void
    {
        if (null !== $d->theme) {
            $this->applyThemePreset($s, $d->theme);
        }

        $socialLinks = $this->mergeSocialLinks($s->getSocialLinks(), $d);
        $logoUrl = $d->logoUrl;
        $primaryColor = $d->primaryColor;
        $secondaryColor = $d->secondaryColor;
        $domain = $d->domain;
        $contactEmail = $d->contactEmail;
        $contactPhone = $d->contactPhone;
        $address = $d->address;

        if (null !== $logoUrl || null !== $primaryColor || null !== $secondaryColor || null !== $domain || null !== $contactEmail || null !== $contactPhone || null !== $address || $socialLinks !== $s->getSocialLinks()) {
            $s->updateContact(
                $logoUrl ?? $s->getLogoUrl(),
                $primaryColor ?? $s->getPrimaryColor(),
                $secondaryColor ?? $s->getSecondaryColor(),
                $domain ?? $s->getDomain(),
                $contactEmail ?? $s->getContactEmail(),
                $contactPhone ?? $s->getContactPhone(),
                $address ?? $s->getAddress(),
                $socialLinks,
            );
        }
        if (null !== $d->slogan) {
            $s->setSlogan($d->slogan);
        }
        if (null !== $d->favicon) {
            $s->setFavicon($d->favicon);
        }
        if (null !== $d->coverImage) {
            $s->setCoverImage($d->coverImage);
        }
        if (null !== $d->description) {
            $s->setDescription($d->description);
        }
        if (null !== $d->fontFamily) {
            $s->setFontFamily($d->fontFamily);
        }
        if (null !== $d->fontSize) {
            $s->setFontSize($d->fontSize);
        }
        if (null !== $d->borderRadius) {
            $s->setBorderRadius($d->borderRadius);
        }
        if (null !== $d->metaPixelId) {
            $metaPixelChanged = $d->metaPixelId !== $s->getMetaPixelId();
            if ($metaPixelChanged && '' !== $d->metaPixelId && (
                !$this->modules->isModuleEnabled('analytics', $boutique)
                || !$this->subscriptionManager->hasExtension('meta_pixel', $boutique)
            )) {
                throw new BadRequestHttpException('Le suivi Meta Pixel nécessite le module Analytics et l\'extension Meta Pixel.');
            }
            $s->setMetaPixelId($d->metaPixelId);
        }
        if (null !== $d->googleAnalyticsId) {
            if ('' !== $d->googleAnalyticsId && !$this->modules->isModuleEnabled('analytics', $boutique)) {
                throw new BadRequestHttpException('Google Analytics nécessite le module Analytics.');
            }
            $s->setGoogleAnalyticsId($d->googleAnalyticsId);
        }
        if (null !== $d->googleTagManagerId) {
            if ('' !== $d->googleTagManagerId && !$this->modules->isModuleEnabled('analytics', $boutique)) {
                throw new BadRequestHttpException('Google Tag Manager nécessite le module Analytics.');
            }
            $s->setGoogleTagManagerId($d->googleTagManagerId);
        }
        if (null !== $d->tiktokPixelId) {
            if ('' !== $d->tiktokPixelId && !$this->modules->isModuleEnabled('analytics', $boutique)) {
                throw new BadRequestHttpException('TikTok Pixel nécessite le module Analytics.');
            }
            $s->setTiktokPixelId($d->tiktokPixelId);
        }
        if (null !== $d->maintenanceMode) {
            $s->setMaintenanceMode($d->maintenanceMode);
        }
        if (null !== $d->maintenanceMessage) {
            $s->setMaintenanceMessage($d->maintenanceMessage);
        }
        if (null !== $d->checkoutMode) {
            $s->setCheckoutMode(\App\Enum\CheckoutMode::tryFrom($d->checkoutMode) ?? $s->getCheckoutMode());
        }
        if (null !== $d->orderMode) {
            $s->setOrderMode(\App\Enum\OrderMode::tryFrom($d->orderMode) ?? $s->getOrderMode());
        }
        // Internal accounts always require email verification. Only the Super Admin
        // may opt customer accounts into the same requirement for this boutique.
        $s->setEnableEmailVerification(true);
        if ($this->context->isSuperAdmin() && null !== $d->enableCustomerEmailVerification) {
            $s->setEnableCustomerEmailVerification($d->enableCustomerEmailVerification);
        }
        if (null !== $d->createAccountAfterOrder) {
            $s->setCreateAccountAfterOrder($d->createAccountAfterOrder);
        }
    }

    private function applyJsonFields(\App\Entity\BoutiqueSettings $s, BoutiqueSettingsInput $d): void
    {
        $colorPalette = $this->mergeColorPalette($s->getColorPalette(), $d);
        if ($colorPalette !== $s->getColorPalette()) {
            $s->setColorPalette($colorPalette);
        }
        if ([] !== $d->iconSet) {
            $s->setIconSet($d->iconSet);
        }
        if ([] !== $d->featuredCategories) {
            $s->setFeaturedCategories($d->featuredCategories);
        }
        if ([] !== $d->frontOfficePages) {
            $s->setFrontOfficePages($d->frontOfficePages);
        }
        if ([] !== $d->navigationItems) {
            $s->setNavigationItems($d->navigationItems);
        }
        if ([] !== $d->guestCheckoutFields) {
            $s->setGuestCheckoutFields($d->guestCheckoutFields);
        }
        if ([] !== $d->contactDetails) {
            $s->setContactDetails($d->contactDetails);
        }
        if ([] !== $d->seoConfig) {
            $s->setSeoConfig($d->seoConfig);
        }
        if ([] !== $d->homepageSections) {
            $s->setHomepageSections($d->homepageSections);
        }
        if ([] !== $d->banners) {
            $s->setBanners($d->banners);
        }
        if ([] !== $d->catalogConfig) {
            $s->setCatalogConfig($d->catalogConfig);
        }
        if ([] !== $d->customerFieldConfig) {
            $s->setCustomerFieldConfig($d->customerFieldConfig);
        }
        if ([] !== $d->notificationConfig) {
            $s->setNotificationConfig($d->notificationConfig);
        }
        if ([] !== $d->moduleConfig) {
            $s->setModuleConfig($d->moduleConfig);
        }
        if ([] !== $d->paymentConfig) {
            $s->setPaymentConfig($d->paymentConfig);
        }
        if ([] !== $d->shippingConfig) {
            $s->setShippingConfig($d->shippingConfig);
        }
        if ([] !== $d->languageConfig) {
            $s->setLanguageConfig($d->languageConfig);
        }
        if ([] !== $d->headerConfig) {
            $s->setHeaderConfig($d->headerConfig);
        }
        if ([] !== $d->footerConfig) {
            $s->setFooterConfig($d->footerConfig);
        }
    }

    /** @param array<string, string> $current */
    private function mergeSocialLinks(array $current, BoutiqueSettingsInput $data): array
    {
        $socialLinks = [];
        $configuredLinks = [] !== $data->socialLinks ? $data->socialLinks : $current;
        foreach ($configuredLinks as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            $normalized = $this->normalizeSocialLink((string) $key, $value);
            if (null !== $normalized) {
                $socialLinks[(string) $key] = $normalized;
            }
        }

        $aliases = [
            'facebook' => $data->facebookUrl,
            'instagram' => $data->instagramUrl,
            'tiktok' => $data->tiktokUrl,
            'youtube' => $data->youtubeUrl,
            'linkedin' => $data->linkedinUrl,
            'x_twitter' => $data->xTwitterUrl,
            'whatsapp' => $data->whatsappNumber,
        ];

        foreach ($aliases as $key => $value) {
            if (null === $value) {
                continue;
            }

            $normalized = $this->normalizeSocialLink($key, $value);
            if (null === $normalized) {
                unset($socialLinks[$key]);
                continue;
            }

            $socialLinks[$key] = $normalized;
        }

        return $socialLinks;
    }

    private function normalizeSocialLink(string $key, string $value): ?string
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        if ('whatsapp' !== $key && !str_starts_with(strtolower($value), 'https://')) {
            throw new BadRequestHttpException(sprintf('Le lien %s doit utiliser https://.', $key));
        }

        if ('whatsapp' === $key && !str_starts_with(strtolower($value), 'https://') && !preg_match('/^\+?[0-9 ()-]{7,}$/', $value)) {
            throw new BadRequestHttpException('Le numéro WhatsApp est invalide.');
        }

        return $value;
    }

    /** @param array<string, string> $current */
    private function mergeColorPalette(array $current, BoutiqueSettingsInput $data): array
    {
        $colorPalette = [] !== $data->colorPalette ? array_replace($current, $data->colorPalette) : $current;

        $aliases = [
            'accent' => $data->accentColor,
            'background' => $data->backgroundColor,
            'text' => $data->textColor,
        ];

        foreach ($aliases as $key => $value) {
            if (null !== $value) {
                $colorPalette[$key] = $value;
            }
        }

        return $colorPalette;
    }

    private function applyThemePreset(\App\Entity\BoutiqueSettings $settings, string $themeCode): void
    {
        $theme = $this->themes->findOneByCode($themeCode);
        if (!$theme instanceof \App\Entity\Theme || !$theme->isActive()) {
            throw new BadRequestHttpException('Thème invalide ou inactif.');
        }

        $preset = $this->themePresets->get($themeCode);
        if (null === $preset) {
            $settings->setTheme($themeCode);

            return;
        }

        $settings->setTheme($themeCode);
        $settings->setColorPalette($preset['colorPalette']);
        $settings->setFontFamily($preset['fontFamily']);
        $settings->setBorderRadius($preset['borderRadius']);
        $settings->updateContact(
            $settings->getLogoUrl(),
            $preset['primaryColor'],
            $preset['secondaryColor'],
            $settings->getDomain(),
            $settings->getContactEmail(),
            $settings->getContactPhone(),
            $settings->getAddress(),
            $settings->getSocialLinks(),
        );
    }
}
