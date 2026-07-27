<?php

namespace App\Dto\Boutique;

use Symfony\Component\Validator\Constraints as Assert;

final class BoutiqueSettingsInput
{
    final public const FONTS = [
        'Inter, system-ui, sans-serif',
        '"Nunito Sans", Rubik, system-ui, sans-serif',
        '"DM Sans", Inter, sans-serif',
        '"Open Sans", system-ui, sans-serif',
        'Lato, "Helvetica Neue", Arial, sans-serif',
        '"Source Sans 3", system-ui, sans-serif',
        '"IBM Plex Sans", system-ui, sans-serif',
        '"Work Sans", system-ui, sans-serif',
        '"Plus Jakarta Sans", system-ui, sans-serif',
        'Montserrat, system-ui, sans-serif',
        'Raleway, system-ui, sans-serif',
        '"Playfair Display", Georgia, serif',
        '"Libre Baskerville", Georgia, serif',
        'Rubik, system-ui, sans-serif',
        '"Space Grotesk", system-ui, sans-serif',
        'Manrope, system-ui, sans-serif',
        'Geist, system-ui, sans-serif',
        '"JetBrains Mono", "Fira Code", monospace',
    ];

    /** @return list<string> */
    public static function getFonts(): array
    {
        return self::FONTS;
    }

    public ?string $shopName = null;
    public ?string $logoUrl = null;
    public ?string $primaryColor = null;
    public ?string $secondaryColor = null;
    public ?string $accentColor = null;
    public ?string $backgroundColor = null;
    public ?string $textColor = null;
    public ?string $domain = null;
    public ?string $contactEmail = null;
    public ?string $contactPhone = null;
    public ?string $address = null;
    public ?string $slogan = null;
    public ?string $favicon = null;
    public ?string $coverImage = null;
    public ?string $description = null;
    #[Assert\Choice(callback: [self::class, 'getFonts'], message: 'Police non reconnue. Choisissez parmi la liste des polices standard.')]
    public ?string $fontFamily = null;
    public ?string $fontSize = null;
    public ?string $borderRadius = null;
    public ?string $theme = null;
    public ?string $checkoutMode = null;
    public ?string $orderMode = null;
    public ?string $metaPixelId = null;
    public ?string $googleAnalyticsId = null;
    public ?string $googleTagManagerId = null;
    public ?string $tiktokPixelId = null;
    public ?bool $maintenanceMode = null;
    public ?string $maintenanceMessage = null;
    public ?bool $enableEmailVerification = null;
    public ?bool $enableCustomerEmailVerification = null;
    public ?bool $createAccountAfterOrder = null;
    public ?string $facebookUrl = null;
    public ?string $instagramUrl = null;
    public ?string $tiktokUrl = null;
    public ?string $youtubeUrl = null;
    public ?string $linkedinUrl = null;
    public ?string $xTwitterUrl = null;
    public ?string $whatsappNumber = null;
    /** @var array<string, string> */
    public array $socialLinks = [];
    /** @var array<string, string> */
    public array $colorPalette = [];
    /** @var array<string, string> */
    public array $iconSet = [];
    /** @var list<array{categoryId?: string, label: string, icon?: string, color?: string, position?: int}> */
    public array $featuredCategories = [];
    /** @var list<array{slug: string, label: string, enabled: bool, position?: int}> */
    public array $frontOfficePages = [];
    /** @var list<array{label: string, href: string, icon?: string, position?: int}> */
    public array $navigationItems = [];
    /** @var list<string> */
    public array $guestCheckoutFields = [];
    /** @var array{mobile?: string, whatsapp?: string, country?: string, state?: string, city?: string, address?: string, postal_code?: string} */
    public array $contactDetails = [];
    /** @var array{meta_title?: string, meta_description?: string, meta_keywords?: string, og_image?: string} */
    public array $seoConfig = [];
    /** @var list<array{type: string, enabled: bool, position?: int, title?: string}> */
    public array $homepageSections = [];
    /** @var list<array{image?: string, mobile_image?: string, title?: string, subtitle?: string, button_text?: string, button_url?: string, active?: bool, position?: int}> */
    public array $banners = [];
    /** @var array{products_per_page?: int, default_sort?: string, show_stock?: bool, show_sku?: bool, show_brand?: bool, show_reviews?: bool, show_related_products?: bool} */
    public array $catalogConfig = [];
    /** @var list<array{field: string, visible?: bool, required?: bool}> */
    public array $customerFieldConfig = [];
    /** @var array{email_notifications?: bool, sms_notifications?: bool, whatsapp_notifications?: bool} */
    public array $notificationConfig = [];
    /** @var array{enable_reviews?: bool, enable_wishlist?: bool, enable_customer_auth?: bool, enable_coupons?: bool, enable_blog?: bool, enable_brands?: bool, enable_multi_address?: bool} */
    public array $moduleConfig = [];
    /** @var array{cod_enabled?: bool, bank_transfer_enabled?: bool, online_payment_enabled?: bool} */
    public array $paymentConfig = [];
    /** @var array{shipping_enabled?: bool, shipping_provider?: string, shipping_api_key?: string, shipping_api_secret?: string} */
    public array $shippingConfig = [];
    /** @var array{default_language?: string, default_currency?: string, timezone?: string} */
    public array $languageConfig = [];
    /** @var array{show_logo?: bool, show_search?: bool, show_cart?: bool, show_account?: bool, show_categories_menu?: bool, show_custom_menu?: bool, show_whatsapp_button?: bool, sticky_header?: bool} */
    public array $headerConfig = [];
    /** @var array{footer_logo?: string, footer_text?: string, copyright_text?: string, show_social_links?: bool, show_newsletter?: bool} */
    public array $footerConfig = [];
}
