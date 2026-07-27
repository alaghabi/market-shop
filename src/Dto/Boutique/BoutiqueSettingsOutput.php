<?php

namespace App\Dto\Boutique;

final class BoutiqueSettingsOutput
{
    public string $id;
    public string $boutiqueId;
    public string $shopName;
    public ?string $logoUrl;
    public ?string $primaryColor;
    public ?string $secondaryColor;
    public ?string $accentColor;
    public ?string $backgroundColor;
    public ?string $textColor;
    public ?string $domain;
    public ?string $contactEmail;
    public ?string $contactPhone;
    public ?string $address;
    public ?string $slogan;
    public ?string $favicon;
    public ?string $coverImage;
    public ?string $description;
    public ?string $fontFamily;
    public ?string $fontSize;
    public ?string $borderRadius;
    public ?string $theme;
    public ?string $checkoutMode;
    public ?string $orderMode;
    public ?string $metaPixelId;
    public ?string $googleAnalyticsId;
    public ?string $googleTagManagerId;
    public ?string $tiktokPixelId;
    public bool $maintenanceMode;
    public ?string $maintenanceMessage;
    public bool $enableEmailVerification;
    public bool $enableCustomerEmailVerification;
    public bool $createAccountAfterOrder;
    public ?string $facebookUrl;
    public ?string $instagramUrl;
    public ?string $tiktokUrl;
    public ?string $youtubeUrl;
    public ?string $linkedinUrl;
    public ?string $xTwitterUrl;
    public ?string $whatsappNumber;
    /** @var array<string, string> */
    public array $socialLinks;
    /** @var array<string, string> */
    public array $colorPalette;
    /** @var array<string, string> */
    public array $iconSet;
    /** @var list<array{categoryId?: string, label: string, icon?: string, color?: string, position?: int}> */
    public array $featuredCategories;
    /** @var list<array{slug: string, label: string, enabled: bool, position?: int}> */
    public array $frontOfficePages;
    /** @var list<array{label: string, href: string, icon?: string, position?: int}> */
    public array $navigationItems;
    /** @var list<string> */
    public array $guestCheckoutFields;
    /** @var array{mobile?: string, whatsapp?: string, country?: string, state?: string, city?: string, address?: string, postal_code?: string} */
    public array $contactDetails;
    /** @var array{meta_title?: string, meta_description?: string, meta_keywords?: string, og_image?: string} */
    public array $seoConfig;
    /** @var list<array{type: string, enabled: bool, position?: int, title?: string}> */
    public array $homepageSections;
    /** @var list<array{image?: string, mobile_image?: string, title?: string, subtitle?: string, button_text?: string, button_url?: string, active?: bool, position?: int}> */
    public array $banners;
    /** @var array{products_per_page?: int, default_sort?: string, show_stock?: bool, show_sku?: bool, show_brand?: bool, show_reviews?: bool, show_related_products?: bool} */
    public array $catalogConfig;
    /** @var list<array{field: string, visible?: bool, required?: bool}> */
    public array $customerFieldConfig;
    /** @var array{email_notifications?: bool, sms_notifications?: bool, whatsapp_notifications?: bool} */
    public array $notificationConfig;
    /** @var array{enable_reviews?: bool, enable_wishlist?: bool, enable_customer_auth?: bool, enable_coupons?: bool, enable_blog?: bool, enable_brands?: bool, enable_multi_address?: bool} */
    public array $moduleConfig;
    /** @var array{cod_enabled?: bool, bank_transfer_enabled?: bool, online_payment_enabled?: bool} */
    public array $paymentConfig;
    /** @var array{shipping_enabled?: bool, shipping_provider?: string, shipping_api_key?: string, shipping_api_secret?: string} */
    public array $shippingConfig;
    /** @var array{default_language?: string, default_currency?: string, timezone?: string} */
    public array $languageConfig;
    /** @var array{show_logo?: bool, show_search?: bool, show_cart?: bool, show_account?: bool, show_categories_menu?: bool, show_custom_menu?: bool, show_whatsapp_button?: bool, sticky_header?: bool} */
    public array $headerConfig;
    /** @var array{footer_logo?: string, footer_text?: string, copyright_text?: string, show_social_links?: bool, show_newsletter?: bool} */
    public array $footerConfig;
    public \DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt;
}
