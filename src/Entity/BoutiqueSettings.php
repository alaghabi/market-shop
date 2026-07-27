<?php

namespace App\Entity;

use App\Enum\CheckoutMode;
use App\Enum\OrderMode;
use App\Repository\BoutiqueSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoutiqueSettingsRepository::class)]
#[ORM\Table(name: 'boutique_settings')]
class BoutiqueSettings extends AbstractEntity
{
    /**
     * @param array<string, string>                                                                                                                                                                       $socialLinks
     * @param array<string, string>                                                                                                                                                                       $colorPalette
     * @param array<string, string>                                                                                                                                                                       $iconSet
     * @param list<array{categoryId?: string, label: string, icon?: string, color?: string, position?: int}>                                                                                              $featuredCategories
     * @param list<array{slug: string, label: string, enabled: bool, position?: int}>                                                                                                                     $frontOfficePages
     * @param list<array{label: string, href: string, icon?: string, position?: int}>                                                                                                                     $navigationItems
     * @param list<string>                                                                                                                                                                                $guestCheckoutFields
     * @param array{mobile?: string, whatsapp?: string, country?: string, state?: string, city?: string, address?: string, postal_code?: string}                                                          $contactDetails
     * @param array{meta_title?: string, meta_description?: string, meta_keywords?: string, og_image?: string}                                                                                            $seoConfig
     * @param list<array{type: string, enabled: bool, position?: int, title?: string}>                                                                                                                    $homepageSections
     * @param list<array{image?: string, mobile_image?: string, title?: string, subtitle?: string, button_text?: string, button_url?: string, active?: bool, position?: int}>                             $banners
     * @param array{products_per_page?: int, default_sort?: string, show_stock?: bool, show_sku?: bool, show_brand?: bool, show_reviews?: bool, show_related_products?: bool}                             $catalogConfig
     * @param list<array{field: string, visible?: bool, required?: bool}>                                                                                                                                 $customerFieldConfig
     * @param array{email_notifications?: bool, sms_notifications?: bool, whatsapp_notifications?: bool}                                                                                                  $notificationConfig
     * @param array{enable_reviews?: bool, enable_wishlist?: bool, enable_customer_auth?: bool, enable_coupons?: bool, enable_blog?: bool, enable_brands?: bool, enable_multi_address?: bool}             $moduleConfig
     * @param array{cod_enabled?: bool, bank_transfer_enabled?: bool, online_payment_enabled?: bool}                                                                                                      $paymentConfig
     * @param array{shipping_enabled?: bool, shipping_provider?: string, shipping_api_key?: string, shipping_api_secret?: string}                                                                         $shippingConfig
     * @param array{default_language?: string, default_currency?: string, timezone?: string}                                                                                                              $languageConfig
     * @param array{show_logo?: bool, show_search?: bool, show_cart?: bool, show_account?: bool, show_categories_menu?: bool, show_custom_menu?: bool, show_whatsapp_button?: bool, sticky_header?: bool} $headerConfig
     * @param array{footer_logo?: string, footer_text?: string, copyright_text?: string, show_social_links?: bool, show_newsletter?: bool}                                                                $footerConfig
     */
    public function __construct(
        #[ORM\OneToOne(inversedBy: 'settings', targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Boutique $boutique,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $logoUrl = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $primaryColor = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $secondaryColor = null,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $domain = null,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $contactEmail = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $contactPhone = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $address = null,
        #[ORM\Column(type: 'json')]
        private array $socialLinks = [],
        #[ORM\Column(length: 80, nullable: true)]
        private ?string $theme = null,
        #[ORM\Column(type: 'json')]
        private array $colorPalette = [
            'primary' => '#7C3AED',
            'primaryContainer' => '#A78BFA',
            'secondary' => '#475569',
            'background' => '#FAF5FF',
            'surface' => '#ffffff',
            'surfaceContainer' => '#F3E8FF',
            'surfaceContainerHigh' => '#E9D5FF',
            'text' => '#0F172A',
            'textMuted' => '#475569',
            'outline' => '#DDD6FE',
            'accent' => '#22C55E',
        ],
        #[ORM\Column(type: 'json')]
        private array $iconSet = [
            'shop' => 'shop',
            'products' => 'bag-shopping',
            'categories' => 'store',
            'orders' => 'cash-register',
            'promotions' => 'tags',
            'loyalty' => 'gift',
        ],
        #[ORM\Column(type: 'json')]
        private array $featuredCategories = [],
        #[ORM\Column(type: 'json')]
        private array $frontOfficePages = [
            ['slug' => 'home', 'label' => 'Accueil', 'enabled' => true, 'position' => 1],
            ['slug' => 'products', 'label' => 'Produits', 'enabled' => true, 'position' => 2],
            ['slug' => 'offers', 'label' => 'Offres', 'enabled' => true, 'position' => 3],
            ['slug' => 'loyalty', 'label' => 'Fidélité', 'enabled' => true, 'position' => 4],
        ],
        #[ORM\Column(type: 'json')]
        private array $navigationItems = [],
        #[ORM\Column]
        private bool $useDeliveryApi = false,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $deliveryApiEndpoint = null,
        #[ORM\Column(length: 50, nullable: true)]
        private ?string $metaPixelId = null,
        #[ORM\Column(length: 32, enumType: CheckoutMode::class)]
        private CheckoutMode $checkoutMode = CheckoutMode::AccountOnly,
        #[ORM\Column]
        private bool $enableEmailVerification = false,
        #[ORM\Column]
        private bool $enableCustomerEmailVerification = false,
        #[ORM\Column]
        private bool $createAccountAfterOrder = false,
        #[ORM\Column(type: 'json')]
        private array $guestCheckoutFields = ['firstname', 'lastname', 'phone', 'email', 'address', 'city', 'postalCode'],
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $slogan = null,
        #[ORM\Column(length: 32, enumType: OrderMode::class)]
        private OrderMode $orderMode = OrderMode::Ecommerce,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $favicon = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $googleAnalyticsId = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $googleTagManagerId = null,
        #[ORM\Column(length: 50, nullable: true)]
        private ?string $tiktokPixelId = null,
        #[ORM\Column]
        private bool $maintenanceMode = false,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $maintenanceMessage = null,
        #[ORM\Column(type: 'json')]
        private array $contactDetails = [],
        #[ORM\Column(type: 'json')]
        private array $seoConfig = [],
        #[ORM\Column(type: 'json')]
        private array $homepageSections = [],
        #[ORM\Column(type: 'json')]
        private array $banners = [],
        #[ORM\Column(type: 'json')]
        private array $catalogConfig = [],
        #[ORM\Column(type: 'json')]
        private array $customerFieldConfig = [],
        #[ORM\Column(type: 'json')]
        private array $notificationConfig = [],
        #[ORM\Column(type: 'json')]
        private array $moduleConfig = [],
        #[ORM\Column(type: 'json')]
        private array $paymentConfig = [],
        #[ORM\Column(type: 'json')]
        private array $shippingConfig = [],
        #[ORM\Column(type: 'json')]
        private array $languageConfig = [],
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $coverImage = null,
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $description = null,
        #[ORM\Column(length: 80, nullable: true)]
        private ?string $fontFamily = null,
        #[ORM\Column(length: 10, nullable: true)]
        private ?string $fontSize = null,
        #[ORM\Column(length: 10, nullable: true)]
        private ?string $borderRadius = null,
        #[ORM\Column(type: 'json')]
        private array $headerConfig = [
            'show_logo' => true,
            'show_search' => true,
            'show_cart' => true,
            'show_account' => true,
            'show_categories_menu' => true,
            'show_custom_menu' => false,
            'show_whatsapp_button' => false,
            'sticky_header' => true,
        ],
        #[ORM\Column(type: 'json')]
        private array $footerConfig = [
            'show_social_links' => true,
            'show_newsletter' => false,
        ],
    ) {
        parent::__construct();
        $boutique->setSettings($this);
    }

    private function touch(): void
    {
        $this->boutique->touchFromSettings();
    }

    public function getBoutique(): Boutique
    {
        return $this->boutique;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function getPrimaryColor(): ?string
    {
        return $this->primaryColor;
    }

    public function getSecondaryColor(): ?string
    {
        return $this->secondaryColor;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    /** @return array<string, string> */
    public function getSocialLinks(): array
    {
        return $this->socialLinks;
    }

    /** @param array<string, string> $socialLinks */
    public function updateContact(
        ?string $logoUrl,
        ?string $primaryColor,
        ?string $secondaryColor,
        ?string $domain,
        ?string $contactEmail,
        ?string $contactPhone,
        ?string $address,
        array $socialLinks,
    ): void {
        $this->logoUrl = $logoUrl;
        $this->primaryColor = $primaryColor;
        $this->secondaryColor = $secondaryColor;
        $this->domain = $domain;
        $this->contactEmail = $contactEmail;
        $this->contactPhone = $contactPhone;
        $this->address = $address;
        $this->socialLinks = $socialLinks;
        $this->touch();
    }

    /** @return array<string, string> */
    public function getColorPalette(): array
    {
        return $this->colorPalette;
    }

    /** @param array<string, string> $colorPalette */
    public function setColorPalette(array $colorPalette): void
    {
        $this->colorPalette = $colorPalette;
        $this->touch();
    }

    /** @return array<string, string> */
    public function getIconSet(): array
    {
        return $this->iconSet;
    }

    /** @param array<string, string> $iconSet */
    public function setIconSet(array $iconSet): void
    {
        $this->iconSet = $iconSet;
        $this->touch();
    }

    /** @return list<array{categoryId?: string, label: string, icon?: string, color?: string, position?: int}> */
    public function getFeaturedCategories(): array
    {
        return $this->featuredCategories;
    }

    /** @param list<array{categoryId?: string, label: string, icon?: string, color?: string, position?: int}> $featuredCategories */
    public function setFeaturedCategories(array $featuredCategories): void
    {
        $this->featuredCategories = $featuredCategories;
        $this->touch();
    }

    /** @return list<array{slug: string, label: string, enabled: bool, position?: int}> */
    public function getFrontOfficePages(): array
    {
        return $this->frontOfficePages;
    }

    /** @param list<array{slug: string, label: string, enabled: bool, position?: int}> $frontOfficePages */
    public function setFrontOfficePages(array $frontOfficePages): void
    {
        $this->frontOfficePages = $frontOfficePages;
        $this->touch();
    }

    /** @return list<array{label: string, href: string, icon?: string, position?: int}> */
    public function getNavigationItems(): array
    {
        return $this->navigationItems;
    }

    /** @param list<array{label: string, href: string, icon?: string, position?: int}> $navigationItems */
    public function setNavigationItems(array $navigationItems): void
    {
        $this->navigationItems = $navigationItems;
        $this->touch();
    }

    public function useDeliveryApi(): bool
    {
        return $this->useDeliveryApi;
    }

    public function enableDeliveryApi(string $endpoint): void
    {
        $this->useDeliveryApi = true;
        $this->deliveryApiEndpoint = $endpoint;
    }

    public function disableDeliveryApi(): void
    {
        $this->useDeliveryApi = false;
        $this->deliveryApiEndpoint = null;
    }

    public function getDeliveryApiEndpoint(): ?string
    {
        return $this->deliveryApiEndpoint;
    }

    public function getMetaPixelId(): ?string
    {
        return $this->metaPixelId;
    }

    public function setMetaPixelId(?string $metaPixelId): void
    {
        $this->metaPixelId = $metaPixelId;
        $this->touch();
    }

    public function getCheckoutMode(): CheckoutMode
    {
        return $this->checkoutMode;
    }

    public function setCheckoutMode(CheckoutMode $checkoutMode): void
    {
        $this->checkoutMode = $checkoutMode;
        $this->touch();
    }

    public function isEnableEmailVerification(): bool
    {
        return $this->enableEmailVerification;
    }

    public function setEnableEmailVerification(bool $enableEmailVerification): void
    {
        $this->enableEmailVerification = $enableEmailVerification;
        $this->touch();
    }

    public function isEnableCustomerEmailVerification(): bool
    {
        return $this->enableCustomerEmailVerification;
    }

    public function setEnableCustomerEmailVerification(bool $enableCustomerEmailVerification): void
    {
        $this->enableCustomerEmailVerification = $enableCustomerEmailVerification;
        $this->touch();
    }

    public function isCreateAccountAfterOrder(): bool
    {
        return $this->createAccountAfterOrder;
    }

    public function setCreateAccountAfterOrder(bool $createAccountAfterOrder): void
    {
        $this->createAccountAfterOrder = $createAccountAfterOrder;
        $this->touch();
    }

    /** @return list<string> */
    public function getGuestCheckoutFields(): array
    {
        return $this->guestCheckoutFields;
    }

    /** @param list<string> $guestCheckoutFields */
    public function setGuestCheckoutFields(array $guestCheckoutFields): void
    {
        $this->guestCheckoutFields = $guestCheckoutFields;
        $this->touch();
    }

    public function getSlogan(): ?string
    {
        return $this->slogan;
    }

    public function setSlogan(?string $slogan): void
    {
        $this->slogan = $slogan;
        $this->touch();
    }

    public function getOrderMode(): OrderMode
    {
        return $this->orderMode;
    }

    public function setOrderMode(OrderMode $orderMode): void
    {
        $this->orderMode = $orderMode;
        $this->touch();
    }

    public function getFavicon(): ?string
    {
        return $this->favicon;
    }

    public function setFavicon(?string $favicon): void
    {
        $this->favicon = $favicon;
        $this->touch();
    }

    public function getGoogleAnalyticsId(): ?string
    {
        return $this->googleAnalyticsId;
    }

    public function setGoogleAnalyticsId(?string $googleAnalyticsId): void
    {
        $this->googleAnalyticsId = $googleAnalyticsId;
        $this->touch();
    }

    public function getGoogleTagManagerId(): ?string
    {
        return $this->googleTagManagerId;
    }

    public function setGoogleTagManagerId(?string $googleTagManagerId): void
    {
        $this->googleTagManagerId = $googleTagManagerId;
        $this->touch();
    }

    public function getTiktokPixelId(): ?string
    {
        return $this->tiktokPixelId;
    }

    public function setTiktokPixelId(?string $tiktokPixelId): void
    {
        $this->tiktokPixelId = $tiktokPixelId;
        $this->touch();
    }

    public function isMaintenanceMode(): bool
    {
        return $this->maintenanceMode;
    }

    public function setMaintenanceMode(bool $maintenanceMode): void
    {
        $this->maintenanceMode = $maintenanceMode;
        $this->touch();
    }

    public function getMaintenanceMessage(): ?string
    {
        return $this->maintenanceMessage;
    }

    public function setMaintenanceMessage(?string $maintenanceMessage): void
    {
        $this->maintenanceMessage = $maintenanceMessage;
        $this->touch();
    }

    /** @return array{mobile?: string, whatsapp?: string, country?: string, state?: string, city?: string, address?: string, postal_code?: string} */
    public function getContactDetails(): array
    {
        return $this->contactDetails;
    }

    /** @param array{mobile?: string, whatsapp?: string, country?: string, state?: string, city?: string, address?: string, postal_code?: string} $contactDetails */
    public function setContactDetails(array $contactDetails): void
    {
        $this->contactDetails = $contactDetails;
        $this->touch();
    }

    /** @return array{meta_title?: string, meta_description?: string, meta_keywords?: string, og_image?: string} */
    public function getSeoConfig(): array
    {
        return $this->seoConfig;
    }

    /** @param array{meta_title?: string, meta_description?: string, meta_keywords?: string, og_image?: string} $seoConfig */
    public function setSeoConfig(array $seoConfig): void
    {
        $this->seoConfig = $seoConfig;
        $this->touch();
    }

    /** @return list<array{type: string, enabled: bool, position?: int, title?: string}> */
    public function getHomepageSections(): array
    {
        return $this->homepageSections;
    }

    /** @param list<array{type: string, enabled: bool, position?: int, title?: string}> $homepageSections */
    public function setHomepageSections(array $homepageSections): void
    {
        $this->homepageSections = $homepageSections;
        $this->touch();
    }

    /** @return list<array{image?: string, mobile_image?: string, title?: string, subtitle?: string, button_text?: string, button_url?: string, active?: bool, position?: int}> */
    public function getBanners(): array
    {
        return $this->banners;
    }

    /** @param list<array{image?: string, mobile_image?: string, title?: string, subtitle?: string, button_text?: string, button_url?: string, active?: bool, position?: int}> $banners */
    public function setBanners(array $banners): void
    {
        $this->banners = $banners;
        $this->touch();
    }

    /** @return array{products_per_page?: int, default_sort?: string, show_stock?: bool, show_sku?: bool, show_brand?: bool, show_reviews?: bool, show_related_products?: bool} */
    public function getCatalogConfig(): array
    {
        return $this->catalogConfig;
    }

    /** @param array{products_per_page?: int, default_sort?: string, show_stock?: bool, show_sku?: bool, show_brand?: bool, show_reviews?: bool, show_related_products?: bool} $catalogConfig */
    public function setCatalogConfig(array $catalogConfig): void
    {
        $this->catalogConfig = $catalogConfig;
        $this->touch();
    }

    /** @return list<array{field: string, visible?: bool, required?: bool}> */
    public function getCustomerFieldConfig(): array
    {
        return $this->customerFieldConfig;
    }

    /** @param list<array{field: string, visible?: bool, required?: bool}> $customerFieldConfig */
    public function setCustomerFieldConfig(array $customerFieldConfig): void
    {
        $this->customerFieldConfig = $customerFieldConfig;
        $this->touch();
    }

    /** @return array{email_notifications?: bool, sms_notifications?: bool, whatsapp_notifications?: bool} */
    public function getNotificationConfig(): array
    {
        return $this->notificationConfig;
    }

    /** @param array{email_notifications?: bool, sms_notifications?: bool, whatsapp_notifications?: bool} $notificationConfig */
    public function setNotificationConfig(array $notificationConfig): void
    {
        $this->notificationConfig = $notificationConfig;
        $this->touch();
    }

    /** @return array{enable_reviews?: bool, enable_wishlist?: bool, enable_customer_auth?: bool, enable_coupons?: bool, enable_blog?: bool, enable_brands?: bool, enable_multi_address?: bool} */
    public function getModuleConfig(): array
    {
        return $this->moduleConfig;
    }

    /** @param array{enable_reviews?: bool, enable_wishlist?: bool, enable_customer_auth?: bool, enable_coupons?: bool, enable_blog?: bool, enable_brands?: bool, enable_multi_address?: bool} $moduleConfig */
    public function setModuleConfig(array $moduleConfig): void
    {
        $this->moduleConfig = $moduleConfig;
        $this->touch();
    }

    /** @return array{cod_enabled?: bool, bank_transfer_enabled?: bool, online_payment_enabled?: bool} */
    public function getPaymentConfig(): array
    {
        return $this->paymentConfig;
    }

    /** @param array{cod_enabled?: bool, bank_transfer_enabled?: bool, online_payment_enabled?: bool} $paymentConfig */
    public function setPaymentConfig(array $paymentConfig): void
    {
        $this->paymentConfig = $paymentConfig;
        $this->touch();
    }

    /** @return array{shipping_enabled?: bool, shipping_provider?: string, shipping_api_key?: string, shipping_api_secret?: string} */
    public function getShippingConfig(): array
    {
        return $this->shippingConfig;
    }

    /** @param array{shipping_enabled?: bool, shipping_provider?: string, shipping_api_key?: string, shipping_api_secret?: string} $shippingConfig */
    public function setShippingConfig(array $shippingConfig): void
    {
        $this->shippingConfig = $shippingConfig;
        $this->touch();
    }

    /** @return array{default_language?: string, default_currency?: string, timezone?: string} */
    public function getLanguageConfig(): array
    {
        return $this->languageConfig;
    }

    /** @param array{default_language?: string, default_currency?: string, timezone?: string} $languageConfig */
    public function setLanguageConfig(array $languageConfig): void
    {
        $this->languageConfig = $languageConfig;
        $this->touch();
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): void
    {
        $this->coverImage = $coverImage;
        $this->touch();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getFontFamily(): ?string
    {
        return $this->fontFamily;
    }

    public function setFontFamily(?string $fontFamily): void
    {
        $this->fontFamily = $fontFamily;
        $this->touch();
    }

    public function getFontSize(): ?string
    {
        return $this->fontSize;
    }

    public function setFontSize(?string $fontSize): void
    {
        $this->fontSize = $fontSize;
        $this->touch();
    }

    public function getBorderRadius(): ?string
    {
        return $this->borderRadius;
    }

    public function setBorderRadius(?string $borderRadius): void
    {
        $this->borderRadius = $borderRadius;
        $this->touch();
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): void
    {
        $this->theme = $theme;
        $this->touch();
    }

    /** @return array{show_logo?: bool, show_search?: bool, show_cart?: bool, show_account?: bool, show_categories_menu?: bool, show_custom_menu?: bool, show_whatsapp_button?: bool, sticky_header?: bool} */
    public function getHeaderConfig(): array
    {
        return $this->headerConfig;
    }

    /** @param array{show_logo?: bool, show_search?: bool, show_cart?: bool, show_account?: bool, show_categories_menu?: bool, show_custom_menu?: bool, show_whatsapp_button?: bool, sticky_header?: bool} $headerConfig */
    public function setHeaderConfig(array $headerConfig): void
    {
        $this->headerConfig = $headerConfig;
        $this->touch();
    }

    /** @return array{footer_logo?: string, footer_text?: string, copyright_text?: string, show_social_links?: bool, show_newsletter?: bool} */
    public function getFooterConfig(): array
    {
        return $this->footerConfig;
    }

    /** @param array{footer_logo?: string, footer_text?: string, copyright_text?: string, show_social_links?: bool, show_newsletter?: bool} $footerConfig */
    public function setFooterConfig(array $footerConfig): void
    {
        $this->footerConfig = $footerConfig;
        $this->touch();
    }
}
