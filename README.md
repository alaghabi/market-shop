# market-shop

Backend Symfony 7.4 / API Platform 4.3 skeleton inspired by the existing `match/backend` project structure.

The local HTTP runtime uses FrankenPHP instead of a separate Nginx + PHP-FPM pair.

## Architecture

```txt
ApiResource / Controller
    -> Dto Input / Output
    -> State Processor / Provider
    -> Service
    -> Entity / Repository / External Adapter
    -> Database / Queue / Cache / External API
```

## Business Model

Hanooti is designed as a multi-boutique SaaS platform. Each boutique owns its own users,
customers, categories, products, stock, orders, promotions, loyalty data, and boutique-specific
sponsors.

```txt
Platform
└── Boutique
    ├── Settings
    ├── Users
    ├── Customers
    ├── Categories
    ├── Products
    │   ├── Images
    │   ├── Stock
    │   └── Promotions
    ├── Orders
    │   └── OrderItems
    ├── Loyalty
    │   ├── LoyaltyAccounts
    │   └── LoyaltyTransactions
    ├── Promotions
    │   ├── Global
    │   ├── Category
    │   └── Product
    └── Sponsors
```

### Main Tables

- `boutique`
- `boutique_settings`
- `app_user`
- `customer`
- `category`
- `product`
- `product_image`
- `product_stock`
- `stock_movement`
- `promotion`
- `promotion_category`
- `promotion_product`
- `customer_order`
- `order_item`
- `loyalty_account`
- `loyalty_transaction`
- `sponsor`
- `boutique_sponsor`

### Roles

- `ROLE_SUPER_ADMIN`: platform administration.
- `ROLE_BOUTIQUE_ADMIN`: boutique administration.
- `ROLE_CAISSIER`: POS/cashier operations.
- `ROLE_CUSTOMER`: customer account access.

### Promotion Priority

Promotion application priority is encoded as:

```txt
Product > Category > Global
```

## API Routes

All routes are mounted under `/api` by API Platform.

### Platform and Boutiques

- `GET /api/boutiques`
- `POST /api/boutiques`
- `GET /api/boutiques/{id}`
- `PATCH /api/boutiques/{id}`
- `DELETE /api/boutiques/{id}`
- `GET /api/boutiques/{boutiqueId}/settings`
- `PATCH /api/boutiques/{boutiqueId}/settings`
- `GET /api/boutiques/{boutiqueId}/theme`
- `PATCH /api/boutiques/{boutiqueId}/theme`

### Boutique Users and Customers

- `GET /api/boutiques/{boutiqueId}/users`
- `POST /api/boutiques/{boutiqueId}/users`
- `GET /api/boutiques/{boutiqueId}/users/{id}`
- `PATCH /api/boutiques/{boutiqueId}/users/{id}`
- `DELETE /api/boutiques/{boutiqueId}/users/{id}`
- `GET /api/boutiques/{boutiqueId}/customers`
- `POST /api/boutiques/{boutiqueId}/customers`
- `GET /api/boutiques/{boutiqueId}/customers/{id}`
- `PATCH /api/boutiques/{boutiqueId}/customers/{id}`
- `DELETE /api/boutiques/{boutiqueId}/customers/{id}`

### Catalogue

- `GET /api/boutiques/{boutiqueId}/categories`
- `POST /api/boutiques/{boutiqueId}/categories`
- `GET /api/boutiques/{boutiqueId}/categories/{id}`
- `PATCH /api/boutiques/{boutiqueId}/categories/{id}`
- `DELETE /api/boutiques/{boutiqueId}/categories/{id}`
- `GET /api/boutiques/{boutiqueId}/products`
- `POST /api/boutiques/{boutiqueId}/products`
- `GET /api/boutiques/{boutiqueId}/products/{id}`
- `PATCH /api/boutiques/{boutiqueId}/products/{id}`
- `DELETE /api/boutiques/{boutiqueId}/products/{id}`
- `GET /api/boutiques/{boutiqueId}/products/{productId}/images`
- `POST /api/boutiques/{boutiqueId}/products/{productId}/images`
- `DELETE /api/boutiques/{boutiqueId}/products/{productId}/images/{id}`

### Stock

- `GET /api/boutiques/{boutiqueId}/products/{productId}/stock`
- `PATCH /api/boutiques/{boutiqueId}/products/{productId}/stock`
- `GET /api/boutiques/{boutiqueId}/stock-movements`
- `POST /api/boutiques/{boutiqueId}/stock-movements`

### Promotions

- `GET /api/boutiques/{boutiqueId}/promotions`
- `POST /api/boutiques/{boutiqueId}/promotions`
- `GET /api/boutiques/{boutiqueId}/promotions/{id}`
- `PATCH /api/boutiques/{boutiqueId}/promotions/{id}`
- `DELETE /api/boutiques/{boutiqueId}/promotions/{id}`

### Orders and POS

- `GET /api/boutiques/{boutiqueId}/orders`
- `POST /api/boutiques/{boutiqueId}/orders`
- `POST /api/boutiques/{boutiqueId}/pos/orders`
- `GET /api/boutiques/{boutiqueId}/orders/{id}`
- `PATCH /api/boutiques/{boutiqueId}/orders/{id}`
- `GET /api/boutiques/{boutiqueId}/orders/{orderId}/items`

### Cart and Checkout

The customer cart is scoped per boutique. The browser stores only a `market_shop_cart_{boutiqueSlug}`
cookie containing the cart ID; cart items, prices, and totals are stored server-side.

- `GET /api/boutiques/{boutiqueId}/cart`
- `POST /api/boutiques/{boutiqueId}/cart/items`
- `PATCH /api/boutiques/{boutiqueId}/cart/items/{itemId}`
- `DELETE /api/boutiques/{boutiqueId}/cart/items/{itemId}`
- `POST /api/boutiques/{boutiqueId}/cart/checkout`

Guest checkout payload:

```json
{
  "customerEmail": "client@example.com",
  "firstName": "Ahmed",
  "lastName": "Ben Ali",
  "phone": "+216 22 000 000",
  "shippingAddress": "Rue de la boutique, immeuble 4",
  "shippingCity": "Tunis"
}
```

### Loyalty

- `GET /api/boutiques/{boutiqueId}/loyalty/accounts`
- `POST /api/boutiques/{boutiqueId}/loyalty/accounts`
- `GET /api/boutiques/{boutiqueId}/loyalty/accounts/{id}`
- `GET /api/boutiques/{boutiqueId}/loyalty/accounts/{accountId}/transactions`
- `POST /api/boutiques/{boutiqueId}/loyalty/accounts/{accountId}/transactions`

### Sponsors

- `GET /api/sponsors`
- `POST /api/sponsors`
- `GET /api/sponsors/{id}`
- `PATCH /api/sponsors/{id}`
- `DELETE /api/sponsors/{id}`
- `GET /api/boutiques/{boutiqueId}/sponsors`
- `POST /api/boutiques/{boutiqueId}/sponsors`
- `DELETE /api/boutiques/{boutiqueId}/sponsors/{id}`

These resources are public API contracts, not Doctrine entities. Persistence and tenant isolation should be implemented in dedicated state providers/processors before production use.

## Deletion Policy

Delete operations must be implemented as logical deletion, not physical deletion.

Entities that can be removed by the business flow implement `SoftDeletableInterface` and use
`SoftDeleteTrait`, which stores deletion state in `deletedAt`:

```txt
DELETE route -> processor/use case -> entity.delete() -> deletedAt set
```

Default collection and item providers should filter out rows where `deletedAt IS NOT NULL`, except
for explicit admin/audit screens.

## UI Template Reference

Primary Stitch template reference:

```txt
https://stitch.withgoogle.com/projects/7338672179219294637?pli=1
```

Use it as the visual baseline for the React dashboard, POS, boutique settings, product catalogue,
orders, promotions, and loyalty screens.

## Boutique Front-Office Personalization

Each boutique can personalize its own customer-facing front-office from the admin dashboard.

Personalizable fields live in `BoutiqueSettings` and are exposed through:

```txt
GET /api/boutiques/{boutiqueId}/theme
PATCH /api/boutiques/{boutiqueId}/theme
```

Supported customization:

- colors: primary, secondary, background, surface, text, muted text, outline
- icons: Font Awesome icon names per module/category/page
- featured categories: label, icon, color, position
- front-office pages: slug, label, enabled, position
- navigation items: label, href, icon, position
- logo, theme name, domain and social links through boutique settings

The React frontend applies the boutique theme as CSS variables, so a boutique admin choice can
change colors and icons without rebuilding the application.

## Main Directories

- `src/ApiResource`: API Platform resources and endpoint declarations.
- `src/Dto`: public API input/output contracts.
- `src/State`: API Platform processors and providers.
- `src/Service`: business logic and external integrations.
- `src/Entity`: Doctrine entities for the multi-boutique business model.
- `src/Repository`: Doctrine repositories.
- `src/Message` and `src/MessageHandler`: asynchronous jobs with Symfony Messenger.
- `src/Security`: authenticators, user provider, token validators.
- `src/Validator`: custom business validators.
- `assets`: optional React/admin frontend compiled with Webpack Encore.
- `etc`: Docker, Makefile fragments, CI/CD, and git hooks.
- `tests`: unit, Postman, and k6 test assets.

## Local Runtime

- `docker-compose.yml`: FrankenPHP app server, PostgreSQL, Redis.
- `etc/docker/frankenphp/Dockerfile`: PHP 8.4 runtime with required extensions.
- `etc/docker/frankenphp/Caddyfile`: FrankenPHP/Caddy HTTP configuration.

## Getting Started

```bash
make install
make start
make test
```

The application is exposed at `http://localhost:8080`.

## Local Login

The application uses local email/password authentication through `/api/auth/login` and
issues HMAC-signed JWT access tokens with `APP_SECRET`. No external OAuth provider is
required. Password verification emails use the SMTP provider configured by `MAILER_DSN`.

## First Feature Pattern

For a new endpoint, create:

- `src/Dto/<Domain>/<Name>Input.php`
- `src/Dto/<Domain>/<Name>Output.php`
- `src/ApiResource/<Domain>/<Name>Resource.php`
- `src/State/<Domain>/<Name>Processor.php`
- `src/Service/<Domain>/<Name>Service.php`
- matching tests under `tests/units/src`
