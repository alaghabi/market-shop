## Goal
Build complete multi-boutique auth, subscription, RBAC & module management system for the Hanooti SaaS platform.

## Constraints & Preferences
- Follow existing codebase patterns (Doctrine entities, API Platform resources, State providers/processors, REST controllers)
- Reuse existing entities/services when possible, avoid duplication
- Multi-boutique SaaS architecture: all data isolated by boutique
- Roles: SUPER_ADMIN, BOUTIQUE_ADMIN, EMPLOYEE (ROLE_CAISSIER), CUSTOMER
- User statuses: PENDING, ACTIVE, SUSPENDED, REJECTED
- PostgreSQL 16 via Docker, all commands inside `docker compose run --rm app php bin/console ...`
- Triple-check module access: PlatformModule.is_enabled → SubscriptionModule.is_allowed → ShopModule.is_enabled
- Permissions checked via RolePermission (role_code → permission code) at backend level only

## Progress
### Done
- **SubscriptionPlan entity**: name, description, durationMonths, priceTnd, isFree, isVisible, isActive, modules (JSON) + OneToMany subscriptionModules
- **SubscriptionPlanModule entity**: 20 modules (reviews→pos) with code, name, description, category, icon, isCore
- **SubscriptionRequest entity**: boutique→Boutique, subscriptionPlan→SubscriptionPlan, status (Pending/Approved/Rejected), requestedAt, approvedAt, approvedBy
- **Subscription entity**: nullable subscriptionPlan ManyToOne
- **API**: SubscriptionPlanResource (CRUD SUPER_ADMIN `/api/admin/subscription-plans`)
- **API**: SubscriptionRequestResource (POST BOUTIQUE_ADMIN `/api/boutiques/{id}/subscription-requests`, approve/reject SUPER_ADMIN)
- **API**: BOUTIQUE_ADMIN GET `/api/boutique/subscription-plans` (active+visible only)
- **Renewal logic**: preserves remaining days on active subscription, fresh start if expired
- **Plans seeded**: Starter (free), Business 3/6/12mois, Premium 12mois — each with SubscriptionModule records
- **Permission entity**: code, name, module, description — master permission list, 65 permissions seeded
- **PlatformModule entity**: module→SubscriptionPlanModule, isEnabled, reasonDisabled — global SUPER_ADMIN toggle
- **ShopModule entity**: boutique→Boutique, module→SubscriptionPlanModule, isEnabled — per-boutique config
- **ModuleAccessService**: triple-check (Platform → Plan → Shop) with `isModuleEnabled()` + `getAccessibleModules()`; uses SubscriptionModule entity + Redis cache (600s TTL); fallback to SubscriptionPlan.modules JSON
- **ModuleCacheService**: Redis-backed cache for platform/plan/shop module maps; `deleteAllPlatform/deletePlan/deleteShop` invalidation
- **SubscriptionModule entity**: plan_id→SubscriptionPlan, module_id→SubscriptionPlanModule, is_allowed — normalized join entity replacing JSON column
- **SubscriptionModuleRepository**: findByPlan, findOneByPlanAndModule, findAllowedByPlan, findAllowedModuleCodes
- **API**: SubscriptionModuleResource (CRUD SUPER_ADMIN `/api/admin/subscription-modules` + `/api/admin/subscription-plans/{planId}/modules`)
- **API**: PermissionResource (CRUD SUPER_ADMIN `/api/admin/permissions`)
- **API**: PlatformModuleResource (CRUD SUPER_ADMIN `/api/admin/platform-modules`)
- **API**: ShopModuleResource (CRUD BOUTIQUE_ADMIN `/api/boutiques/{id}/modules`)
- **DTOs+Providers+Processors**: Permission, PlatformModule, ShopModule, SubscriptionModule — following existing patterns
- **Permissions seeded**: 65 permissions across catalogue, commandes, clients, cms, employés, marketing, facturation, abonnements, boutique, rapports
- **Migration** `Version20260629084955`: added `icon`/`is_core` to subscription_plan_module, created permission/platform_module/shop_module tables
- **Migration** `Version20260629085037`: removed `is_core` default
- **Migration** `Version20260629090001`: created subscription_module table with unique (plan_id, module_id)
- **Schema in sync**, CS fixer clean, tests pass (1 test, 3 assertions)

### Done (this session)
- **BoutiqueSettings entity** — added 22 new fields (theme, coverImage, description, fontFamily, fontSize, borderRadius, headerConfig, footerConfig, deliveryApiEndpoint, analytics, loyalty, orderMode, maintenance, socialLinks, navigationItems, guestCheckoutFields, slogan, favicon, enableEmailVerification, enableCustomerEmailVerification, createAccountAfterOrder, enableLoyalty) with full getters/setters
- **BoutiqueSettingsInput/Output DTOs** — all ~40 fields including JSON columns
- **BoutiqueSettingsResource updated** — uses SettingsProvider/SettingsProcessor instead of passthrough; maps 40+ fields
- **SettingsProvider** — full BoutiqueSettings → BoutiqueSettingsOutput mapping (40+ fields)
- **SettingsProcessor** — PATCH with `applyScalarFields` + `applyJsonFields`, `updateContact` fix, cache invalidation
- **Theme entity** — name, code (unique), previewImage, isActive, isDefault, timestamps
- **ThemeRepository** — findActive, findDefault, findOneByCode, clearDefault
- **ThemeInput/Output DTOs** — name, code, previewImage, isActive, isDefault
- **ThemeResource** — SUPER_ADMIN CRUD `/api/admin/themes` + PUBLIC GET `/api/boutiques/{id}/themes`
- **ThemeProvider/Processor** — SUPER_ADMIN CRUD with default theme clearing
- **Menu entity** — boutique FK, name, position (HEADER/FOOTER/OTHER), isActive, OneToMany items, timestamps
- **MenuItem entity** — menu FK, title, type (HOME/PAGE/CATEGORY/PRODUCT/URL/CONTACT), target, parent self-ref FK, position, isActive, timestamps
- **MenuRepository/MenuItemRepository** — boutique-scoped queries
- **MenuInput/Output, MenuItemInput/Output DTOs** — all specs with parent hierarchy
- **MenuResource** — boutique-scoped CRUD + nested menu item CRUD
- **MenuProvider/Processor** — boutique-scoped with nested items serialization, parent hierarchy
- **Announcement entity rewritten** — boutique FK (nullable for global), content, displayType, title, colors, icon, linkUrl, priority, isDismissible, displayMode (FIXED/SCROLLING/SLIDER), position (TOP_PAGE/ABOVE_HEADER/BELOW_HEADER/ABOVE_FOOTER/HOME_TOP/HOME_BOTTOM), displayPages JSON, active, isGlobal, startsAt, endsAt, isVisible()
- **AnnouncementInput/Output DTOs** — all specs (split to individual files for PSR-4 autoloading)
- **AnnouncementResource** — boutique-scoped CRUD + global SUPER_ADMIN endpoints
- **AnnouncementProvider/Processor** — boutique + global support, cache invalidation
- **FrontOfficeCacheService** — Redis cache for `shop:{id}:settings` and `shop:{id}:menus`, serializeSettings() method, invalidateAll()/invalidateSettings()/invalidateMenus()/invalidateSeo()
- **Migration** `Version20260629093104` — created theme, menu, menu_item tables; added announcement columns (title, border_color, icon, link_url, priority, is_dismissible, display_mode, position, display_pages, is_global); added boutique_settings columns (cover_image, description, font_family, font_size, border_radius, header_config, footer_config)
- **Security.yaml** — added PUBLIC_ACCESS routes for settings, menus, themes; SUPER_ADMIN routes for admin/themes and admin/announcements
- **PHP-CS-Fixer clean**, schema validated, tests pass (1 test, 3 assertions)

### Done (this session - route migration)
- **Front-office route migration to subdomain** — removed `{boutiqueId}` from 35 API Platform resource URIs (products, categories, brands, cart, reviews, promotions, coupons, delivery-rules, payment-methods, cms, announcements, settings, menus, themes, media, conversations, sponsors, subscriptions, subscription-requests, notification-templates, refunds, invoices, filters, favorites, loyalty, chat, inventory, delivery-accounts)
- **Provider migration** — updated 23 providers to use `BoutiqueAwareProviderTrait::resolveBoutiqueFromRequest()` instead of `BoutiqueRepository::findBySlugOrId()` (Product, Category, Brand, Review, Promotion, ShopPaymentMethod, CmsPage, Settings, Menu, Media, BoutiqueSponsor, Subscription, SubscriptionRequest, NotificationTemplate, Invoice, BoutiqueDeliveryAccount, ProductFilter, Cart, Coupon, DeliveryRule, Theme, Announcement, Conversation)
- **security.yaml updated** — replaced `/api/boutiques/[^/]+/...` patterns with subdomain-based patterns (`/api/products`, `/api/categories`, etc.); removed stale `boutiques/[^/]+/` patterns
- **InvoicePdfService extracted to Twig** — `renderHtml()` now uses `$this->twig->render('invoice/show.html.twig', [...])`; template at `templates/invoice/show.html.twig` with full HTML/CSS layout

### Done (this session)
- **ShopContext service** — `src/Service/Boutique/ShopContext.php` with Redis cache (6h TTL), `getCurrentShop()`/`getCurrentShopId()`/`getCurrentSlug()`/`clearCache()`/`clearAllCache()`; resolves boutique from subdomain via `SubdomainResolver`, falls back to query/path for admins; `RequestStack`-based, sets `_boutique` request attribute for downstream use
- **ReservedSlugRegistry** — `src/Service/Boutique/ReservedSlugRegistry.php` with 40+ default reserved words (admin, api, www, etc.) + configurable `$additional` array from `%app.reserved_slugs%` parameter
- **Slug validation** — updated `BoutiqueInput` with 5 regex constraints (lowercase+digits+hyphens only, start with letter, end with letter/digit, no double hyphens, min 3 chars) + French error messages
- **Processor validation** — updated `BoutiqueProcessor` with `validateSlug()`: checks `ReservedSlugRegistry::isReserved()` then `BoutiqueRepository::findBySlug()` uniqueness at app level before DB constraint; throws `BadRequestHttpException`/`ConflictHttpException`
- **Boutique entity** — added `getSubdomainUrl(): string` returning `https://{slug}.hanooti.com`
- **BoutiqueOutput** — added `subdomainUrl` field, mapped in `BoutiqueProcessor::toOutput()`
- **Access control** — rewrote `BoutiqueRequestSubscriber` to enforce status-based access: PENDING/SUSPENDED only for admins, REJECTED/ARCHIVED returns 404 for public
- **Cache invalidation** — `BoutiqueCacheSubscriber` Doctrine listener (postUpdate + preRemove) clears Redis cache key on Boutique changes/deletion
- **Service registration** — `ShopContext` (with `$rootDomain`), `ReservedSlugRegistry` (with `%app.reserved_slugs%`) in `services.yaml`; `app.reserved_slugs` + `shop_context` cache TTL in `parameters.yaml`

### Resolved (this session)
- **404 sur ops boutique personnalisées** — `suspend`, `activate`, `publish`, `unpublish`, `approve`, `reject`, `archive`, `DELETE` retournaient 404 malgré routes et security corrects.
  - **Cause** : ces opérations manquaient de `provider: BoutiqueProvider::class`. `BoutiqueResource` est un DTO (pas Doctrine), API Platform ne pouvait pas résoudre l'entité → `ReadProvider` retourne null → 404.
  - **Fix** : ajouté `provider: BoutiqueProvider::class` sur les 8 opérations dans `src/ApiResource/Boutique/BoutiqueResource.php`.
  - **Testé** : curl avec token super-admin → 200 pour `suspend`, `activate`, `approve`, `unpublish`.

### Blocked
- *(none)*

## Key Decisions
- `SubscriptionPlanModule` reused as master module definition (no separate Module entity)
- Plan-level module gating uses existing `SubscriptionPlan.modules` JSON column as fallback; primary source is SubscriptionModule join entity
- Triple-check via ModuleAccessService: PlatformModule (global) → SubscriptionPlan.modules (plan) → ShopModule (boutique)
- RolePermission uses `role_code` + `permission` string directly — no join table for roles
- PlanType enum kept for backward compatibility with initial Free subscription; all new flows use SubscriptionPlan
- Permissions created via dedicated entity (not embedded in modules) for flexible role→permission mapping

### Done (this session)
- **Webhook async dispatch** — `DispatchWebhookEventMessage` + handler routes to `WebhookService.dispatchEventSync()` via Messenger `async` transport
- **WebhookService updated** — `dispatchEvent()` (async via Messenger bus) + `dispatchEventSync()` (synchronous for handler)
- **messenger.yaml** — added `App\Message\DispatchWebhookEventMessage: async` routing
- **RefundCacheService** — fixed to use `RedisFactory` (PHP Redis extension) instead of `Predis\ClientInterface`
- **CouponCacheService** — fixed to use `RedisFactory` instead of `Predis\ClientInterface`
- **DeliveryCacheService** — fixed to use `RedisFactory` instead of `Predis\ClientInterface`
- **DeliveryRule entity** — fixed constructor default for `\DateTimeImmutable` (not valid in PHP promoted properties)
- **PromotionsEngine** — fixed to use correct `PromotionType::Percentage/FixedAmount` enums and `findActiveByBoutique(Boutique)` interface
- **Webhook API Resource** — SUPER_ADMIN CRUD `/api/admin/webhooks` (GET, POST, PUT, DELETE) with `WebhookInput`/`WebhookOutput` DTOs, `WebhookProvider`, `WebhookProcessor`
- **AuditLog API Resource** — SUPER_ADMIN read-only `/api/admin/audit-logs` (GET, GET collection) with `AuditLogOutput` DTO and `AuditLogProvider`
- **CustomerNotification API Resource** — customer-facing endpoints: `GET /api/me/notifications`, `POST /api/me/notifications/{id}/read`, `POST /api/me/notifications/read-all` with `CustomerNotificationOutput` DTO, `CustomerNotificationProvider`, `CustomerNotificationProcessor`
- **InvoicePdfService** — fixed PHP syntax error in heredoc (ternary operator extraction before heredoc)
- **Migration `Version20260629142125`** — created 8 tables: `audit_log`, `coupon`, `coupon_category`, `coupon_product`, `customer_notification`, `refund`, `refund_item`, `webhook`

### Done (this session)
- **Cache key colon fix** — replaced `:` with `.` in 10 cache service files (FrontOfficeCacheService, AppConfigService, MediaCacheService, CmsCacheService, DashboardCacheService, MarketingCacheService, NotificationCacheService, SessionCacheService, Billing/InvoiceCacheService, FavoriteCacheService) + NotificationTemplateProvider; filesystem adapter rejects `:` in keys
- **UserShop fix** — moved `$createdAt = new \DateTimeImmutable()` into constructor body (NOT NULL column, PHP 8.4 rejects `new` as default property value in this context)
- **Deleted skeleton** — removed `src/Dto/Boutique/BoutiqueDtoCollection.php` (empty file causing autoload error)
- **ProductFilterProcessor fixes** — slug generation order (`strtolower` before regex), repository injection (was using generic `EntityManager::getRepository`), `Boutique` → `string` cast for repository methods
- **Verified API works** — logged in as super-admin, created boutique admin user, logged in as boutique admin, created categories, products, and filters via flat `/api/*` endpoints

### Done (this session)
- **Modular BackOffice frontend built** — complete React+TypeScript admin UI under `assets/react/backoffice/` with reusable components, layout, and 11 page modules
- **Reusable components** — Button (4 variants), Card/Header/Body/Footer, Modal, Table (sortable, row clicks), Pagination, Badge (5 tones), Loading/Empty/Error states, Toast (auto-dismiss 4s), ConfirmDialog (danger mode), FormField/Input/Select/Textarea, FiltersBar (search + status + custom)
- **Layout** — Shell (Sidebar + Header + Content), Sidebar (RBAC-filtered, 17 nav items, 6 sections, SVG icons), Header (search, boutique selector dropdown, user menu with roles/logout), PageHeader
- **CSS design system** — `assets/styles/backoffice.css` with 80+ CSS variables, all layout classes, responsive breakpoints
- **Pages created** — DashboardPage, ProductsPage (full CRUD + flat `/api/products`), CategoriesPage, FiltersPage, OrdersPage, CustomersPage, PromotionsPage, CmsManagementPage, SettingsPage, EmployeesPage, SubscriptionsPage, SuperAdminPage
- **BackOfficeApp.tsx** — entry point with routing, boutique context, notification provider, auto-fetch boutiques on mount
- **App.tsx integration** — replaced `<BackOfficeRoutePage><BackOffice .../></BackOfficeRoutePage>` with new `<BackOfficeApp .../>` for all admin routes
- **backoffice.css imported** — added `import '../styles/backoffice.css'` to `main.tsx`
- **Page patterns** — every page follows: `PageHeader → Card → FiltersBar → Table → Pagination → Modal → ConfirmDialog`; consistent styles, loading states, CRUD flows
- **State coverage** — all pages handle loading, empty, and error states with retry

### Done (this session - backoffice pages)
- **3 pages super-admin extraites** :
  - `pages/boutiques/BoutiquesPage.tsx` — CRUD boutiques + demandes abonnement + publish/unpublish workflow
  - `pages/statistics/StatistiquesPage.tsx` — KPIs plateforme, grille stats modules, top boutiques CA
  - `pages/analytics/AnalyticsPage.tsx` — analytiques, thèmes, logs & audit, monitoring Redis/queue/files
- **Sidebar** — ajout des entrées Statistiques (`chart`) et Analyse & Monitoring (`activity`); icône `activity` SVG ajoutée
- **BackOfficeApp.tsx** — routes et guards (`ROLE_SUPER_ADMIN`) pour les 3 slugs: `boutiques`, `statistics`, `analytics`
- **SuperAdminPage** — transformée en hub/tableau de navigation (6 cartes) vers les pages spécialisées; sections extraites supprimées
- **Bug fix** — pluriel `nouveau{x}` corrigé dans StatistiquesPage (condition vérifiée dynamiquement)
- **TypeScript + webpack** — clean, aucun warning build

### Done (this session - compte admin / account subscription)
- **AccountSubscription entity** — user FK (CASCADE), subscriptionPlan FK (SET NULL), status (active/expired/replaced via `AccountSubscriptionStatus` enum), startDate/endDate, replacedBy self-ref, createdAt/updatedAt, changedBy
- **AccountSubscriptionExtension entity** — grant join (subscription×extension, unique), activatedAt/expiresAt/activatedBy, isActive, expiryNotifiedAt
- **AccountSubscriptionService** — getActiveSubscription (lecture seule, pas de mutation), getMaxBoutiquesForPlanAndExtensions (null = illimité ; clé absente max_boutiques = illimité), getPublishedBoutiqueCount, getBoutiqueCreationCap, cache Redis `-1`↔`null` avec hit detection, expireOverdueSubscriptions (cron)
- **AccountSubscriptionRepository** — findActiveByUser, findExpiredButStillActive (alias DQL `sub`), findAllPaginated
- **AccountSubscriptionCacheSubscriber** — Doctrine listener invalidant le cache Redis à chaque changement
- **API `AccountSubscriptionResource`** — 4 opérations ROLE_BOUTIQUE_ADMIN : GET `/api/account/subscription` (output `publishedBoutiques`), POST subscribe (refuse downgrade si trop de boutiques publiées), POST renew, GET change-preview
- **API publique** — GET `/api/public/subscription-plans` (plans actifs+visibles, sans redirect 401)
- **API admin** — GET+POST `/api/admin/account-subscriptions` (GetCollection + create)
- **Migration `Version20260731094000`** — tables + index partiel unique `(user_id) WHERE status='active'` + index `(user_id, status)`
- **Commands** — SeedExtensionsCommand, SeedQuotaDefinitionsCommand (Premium `max_boutiques => null`), ExtensionExpiryCommand, AccountSubscriptionExpiryCommand
- **Frontend** — `AccountSubscriptionPanel.tsx` (quota = publiées), `AdminAccountSubscriptionPanel.tsx` (picker via `/admin/boutique-admins`), `SubscriptionPlansSlider` (fetch public natif)
- **Bug sécurité security.yaml** — règle générique `{ path: ^/api/account, roles: ROLE_CUSTOMER }` matchait avant `/api/account/subscription` → 403 ; règle spécifique déplacée avant la générique
- **Bug provider GET body null** — `read: false` sur les Get court-circuitait le provider ; retiré (le `/subscription/summary` fonctionne sans), POST gardent `read: false` + `status: 200`
- **Bug authentification** — `BearerTokenAuthenticator` renvoie `InMemoryUser` pour tokens locaux ; `AccountSubscriptionProvider`/`Processor` résolvent désormais le vrai `User` via `UserRepository::findOneBy(['identifier' => ...])`
- **Tests** — `tests/functional/Api/AccountSubscriptionApiTest.php` (4 tests) : read/subscribe/renew/change-preview du flux complet

### Done (this session - SUPER_ADMIN abonnement + publication multi-boutiques + nettoyage Microsoft + Home page plans slider)
- **Besoin 1: SUPER_ADMIN crée abonnements validés** —
  - `AdminAccountSubscriptionInput` DTO (userId, planId, extensionIds[], startDate, endDate)
  - `AdminAccountSubscriptionResource` GET+POST `/api/admin/account-subscriptions` (ROLE_SUPER_ADMIN)
  - `AdminAccountSubscriptionProcessor` crée AccountSubscription Active, sync extensions, flush puis invalidate, audit log
  - Seeder: plan **Business 1 mois** (4900 TND, 1 mois, max_boutiques=2) ; Premium quotas `null` (illimité)
  - Frontend: `AdminAccountSubscriptionPanel.tsx` — picker via `/admin/boutique-admins`, plan, extensions cumulables, dates optionnelles
- **Besoin 2: Admin boutique = multi-boutiques + extensions cumulables + auto-publication** —
  - `BoutiqueProcessor.create()` : transaction boutique+UserShop, plafond soft création (`getBoutiqueCreationCap`), `setOwner($user)`
  - `BoutiqueProcessor.publishBoutique()` : contrôle owner, `approvedAt`, quota publié via `countPublishedByOwner` + extensions
  - Downgrade refusé si `publishedBoutiques > newMax`
  - DashboardPage: `api.patch` publish/unpublish ; `isOwner` via `access.userId === boutique.ownerId`
- **Home Page : Subscription Plans Slider** — fetch `/api/public/subscription-plans` (pas ApiClient, pas 401 redirect)
- **Revue code appliquée** : secret Google vidé de `.env.prod`, cache -1/null, Premium null, endpoint public, index unique partiel, cron expiry, CSS `.bo-btn-success`
- **Vérifications** : php -l OK, phpstan clean, tsc --noEmit OK, 70 tests / 236 assertions OK

## Next Steps
1. Add permission-check middleware/gate for API Platform operations
2. Update BoutiqueContext to support permission-based access (role + permission per boutique)
3. Create SUPER_ADMIN UI for module/plan management
4. Create BOUTIQUE_ADMIN UI for shop module config + employee permissions
5. Add cache layer for user permissions (6h TTL, invalidate on changes)
6. Wire webhook dispatch events from OrderProcessor, InvoiceProcessor, RefundProcessor, etc.
7. Complete CustomerNotificationProvider with proper customer resolution from JWT token
8. AuditLogProcessor for write operations (currently read-only)

## Critical Context
- All commands inside Docker: `docker compose run --rm app php bin/console ...`
- RBAC tables (user_shop, role_permission) from migration `Version20260627100000`
- Subscription tables from migration `Version20260629084023` + `Version20260629084955`
- New tables (migration `Version20260629142125`): `refund`, `refund_item`, `coupon`, `coupon_category`, `coupon_product`, `delivery_rule` (from earlier), `webhook`, `audit_log`, `customer_notification`
- 20 modules: reviews, wishlist, loyalty, coupons, promotions, blog, brands, multi_address, chatbot, seo_advanced, custom_domain, analytics, delivery_tracking, wholesale, gift_cards, newsletter, abandoned_cart, order_printing, social_login, pos
- 5 plans: Starter (0 DT), Business 3mois (99 DT), 6mois (179 DT), 12mois (299 DT), Premium 12mois (499 DT)
- 65 permissions seeded across 10 modules (catalogue, commandes, clients, cms, employés, marketing, facturation, abonnements, boutique, rapports)
- Redis cache uses `RedisFactory` (PHP `Redis` extension), NOT `Predis`

## Relevant Files
- `src/Entity/SubscriptionPlan.php` — plan definition with modules JSON
- `src/Entity/SubscriptionPlanModule.php` — module definition with icon + isCore
- `src/Entity/SubscriptionRequest.php` — request/approve/reject flow
- `src/Entity/Subscription.php` — added subscriptionPlan FK
- `src/Entity/Permission.php` — master permission list (65 seeded)
- `src/Entity/PlatformModule.php` — global SUPER_ADMIN toggle
- `src/Entity/ShopModule.php` — per-boutique module config
- `src/Entity/RolePermission.php` — role→permission mapping
- `src/Entity/Refund.php` — refund entity with boutique/order/customer
- `src/Entity/RefundItem.php` — refund line items
- `src/Entity/Coupon.php` — coupon with type/scope/usage limits
- `src/Entity/CouponProduct.php`, `src/Entity/CouponCategory.php` — coupon scoped associations
- `src/Entity/DeliveryRule.php` — delivery rules with weight/distance/cart constraints
- `src/Entity/Webhook.php` — webhook with HMAC signature, auto-disable
- `src/Entity/AuditLog.php` — centralized audit trail
- `src/Entity/CustomerNotification.php` — customer-facing notifications
- `src/Message/DispatchWebhookEventMessage.php` — async webhook message
- `src/MessageHandler/DispatchWebhookEventMessageHandler.php` — async handler
- `src/Service/Webhook/WebhookService.php` — webhook CRUD + dispatch
- `src/Service/Webhook/WebhookDispatcher.php` — HTTP POST with HMAC
- `src/Service/Billing/RefundService.php` — refund logic + credit note generation
- `src/Service/Billing/RefundCacheService.php` — Redis cache (RedisFactory)
- `src/Service/Billing/InvoicePdfService.php` — dompdf PDF generation
- `src/Service/Marketing/CouponService.php` — coupon validation + application
- `src/Service/Marketing/CouponCacheService.php` — Redis cache (RedisFactory)
- `src/Service/Marketing/PromotionsEngine.php` — discount calculation
- `src/Service/Delivery/DeliveryRuleService.php` — delivery fee calculation
- `src/Service/Delivery/DeliveryCacheService.php` — Redis cache (RedisFactory)
- `src/Service/Audit/AuditLogService.php` — audit log creation
- `src/Service/CustomerNotification/CustomerNotificationService.php` — notification CRUD
- `src/ApiResource/Webhook/WebhookResource.php` — SUPER_ADMIN CRUD
- `src/ApiResource/AuditLog/AuditLogResource.php` — SUPER_ADMIN read-only
- `src/ApiResource/CustomerNotification/CustomerNotificationResource.php` — customer-facing
- `src/Dto/Webhook/WebhookInput.php`, `src/Dto/Webhook/WebhookOutput.php`
- `src/Dto/AuditLog/AuditLogOutput.php`
- `src/Dto/CustomerNotification/CustomerNotificationOutput.php`
- `src/State/Webhook/WebhookProvider.php`, `src/State/Webhook/WebhookProcessor.php`
- `src/State/AuditLog/AuditLogProvider.php`
- `src/State/CustomerNotification/CustomerNotificationProvider.php`, `src/State/CustomerNotification/CustomerNotificationProcessor.php`
- `config/packages/messenger.yaml` — async transport + routing (webhook + notification)
- `migrations/Version20260629142125.php` — refund, coupon, webhook, audit_log, customer_notification tables
- `src/Service/Module/ModuleAccessService.php` — triple-check module access
- `src/Command/SeedSubscriptionModulesCommand.php` — 20 modules with icons
- `src/Command/SeedSubscriptionPlansCommand.php` — 5 plans
- `src/Command/SeedPermissionsCommand.php` — 65 permissions
- `src/ApiResource/SubscriptionPlan/SubscriptionPlanResource.php`
- `src/ApiResource/SubscriptionRequest/SubscriptionRequestResource.php`
- `src/ApiResource/Permission/PermissionResource.php`
- `src/ApiResource/PlatformModule/PlatformModuleResource.php`
- `src/ApiResource/ShopModule/ShopModuleResource.php`
- `migrations/Version20260629084955.php` — icon/is_core + 3 new tables
- `assets/react/backoffice/` — new modular BackOffice code (components, layout, hooks, api, types, pages, BackOfficeApp.tsx)
- `assets/react/backoffice/components/*.tsx` — reusable UI components (Button, Card, Modal, Table, Pagination, Badge, States, Toast, ConfirmDialog, FormField, FiltersBar)
- `assets/react/backoffice/layout/Shell.tsx` — Shell, PageHeader
- `assets/react/backoffice/layout/Sidebar.tsx` — RBAC nav with SVG icons
- `assets/react/backoffice/layout/Header.tsx` — boutique selector, user menu
- `assets/react/backoffice/pages/*/` — 12 page modules (dashboard, products, categories, filters, orders, customers, promotions, cms, settings, employees, subscriptions, super-admin)
- `assets/styles/backoffice.css` — complete design system (80+ CSS variables)
- `assets/react/components/SubscriptionPlansSlider.tsx` — animated plans carousel for public home page
- `assets/react/pages/home/index.tsx` — public home page with integrated plans slider
