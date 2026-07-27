<?php

namespace App\DataFixtures;

use App\Entity\Announcement;
use App\Entity\Boutique;
use App\Entity\BoutiqueDeliveryAccount;
use App\Entity\BoutiqueSettings;
use App\Entity\Brand;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Category;
use App\Entity\ChatbotConfig;
use App\Entity\CmsBlock;
use App\Entity\CmsPage;
use App\Entity\Conversation;
use App\Entity\Coupon;
use App\Entity\Country;
use App\Entity\Customer;
use App\Entity\CustomerNotification;
use App\Entity\DeliveryCompany;
use App\Entity\DeliveryEndpoint;
use App\Entity\DeliveryRule;
use App\Entity\Governorate;
use App\Entity\Locality;
use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Entity\Message;
use App\Entity\Order;
use App\Entity\PaymentMethod;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\ProductFilter;
use App\Entity\ProductFilterValue;
use App\Entity\ProductImage;
use App\Entity\ProductProperty;
use App\Entity\ProductStock;
use App\Entity\ProductVariant;
use App\Entity\ProductVariantAttribute;
use App\Entity\Promotion;
use App\Entity\Review;
use App\Entity\ShopPaymentMethod;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\Theme;
use App\Entity\User;
use App\Entity\UserShop;
use App\Enum\CartStatus;
use App\Enum\CmsBlockType;
use App\Enum\CmsPageStatus;
use App\Enum\CmsPageType;
use App\Enum\CouponScope;
use App\Enum\CouponType;
use App\Enum\DeliveryAuthType;
use App\Enum\DeliveryEndpointType;
use App\Enum\DeliveryRuleType;
use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Enum\PaymentMethodType;
use App\Enum\PaymentStatus;
use App\Enum\PlanType;
use App\Enum\ProductStatus;
use App\Enum\PromotionScope;
use App\Enum\PromotionType;
use App\Enum\SubscriptionStatus;
use App\Enum\UserStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

final class AppFixtures extends Fixture
{
    private Generator $faker;

    public function load(ObjectManager $manager): void
    {
        $this->faker = Factory::create('fr_FR');

        $country = $this->createCountry($manager);
        $governorates = $this->createGovernorates($manager, $country);
        $localities = $this->createLocalities($manager, $governorates);
        $paymentMethods = $this->createPaymentMethods($manager);
        $deliveryCompany = $this->createDeliveryCompany($manager);
        $subscriptionPlan = $this->createSubscriptionPlan($manager);

        $manager->persist(new Theme('Hanooti Glass', 'hanooti-glass', '/images/themes/hanooti-glass.jpg', true, false));
        $manager->persist(new Theme('Hanooti Marketplace', 'hanooti-marketplace', '/images/themes/hanooti-marketplace.jpg', true, true));
        $manager->persist(new Theme('Nordic Editorial', 'nordic-editorial', '/images/themes/nordic-editorial.jpg', true, false));
        $manager->persist(new Theme('Ocean Minimal', 'ocean-minimal', '/images/themes/ocean-minimal.jpg', true, false));

        $superAdmin = $this->createUser($manager, null, 'super-admin.fixture@hanooti.local', ['ROLE_SUPER_ADMIN'], 'Super', 'Admin');

        $boutiqueDefinitions = [
            ['Hanooti Demo Store', 'demo-hanooti', 'Mode, maison et epicerie fine.'],
            ['Beauty Lab Demo', 'demo-beauty-lab', 'Cosmetiques, parfums et soins.'],
            ['Tech Corner Demo', 'demo-tech-corner', 'Accessoires tech et objets connectes.'],
        ];

        foreach ($boutiqueDefinitions as [$name, $slug, $description]) {
            $this->createBoutiqueGraph(
                $manager,
                $name,
                $slug,
                $description,
                $superAdmin,
                $subscriptionPlan,
                $paymentMethods,
                $deliveryCompany,
                $country,
                $governorates,
                $localities,
            );
        }

        $manager->flush();
    }

    private function createCountry(ObjectManager $manager): Country
    {
        $country = new Country('Tunisie', 'TN', '216');
        $manager->persist($country);

        return $country;
    }

    /** @return list<Governorate> */
    private function createGovernorates(ObjectManager $manager, Country $country): array
    {
        $data = [
            ['Tunis', 'TN-11'],
            ['Ariana', 'TN-12'],
            ['Sousse', 'TN-51'],
        ];
        $governorates = [];

        foreach ($data as [$name, $code]) {
            $governorate = new Governorate($country, $name, $code);
            $manager->persist($governorate);
            $governorates[] = $governorate;
        }

        return $governorates;
    }

    /**
     * @param list<Governorate> $governorates
     *
     * @return list<Locality>
     */
    private function createLocalities(ObjectManager $manager, array $governorates): array
    {
        $localities = [];

        foreach ($governorates as $index => $governorate) {
            foreach (['Centre', 'Nord', 'Sud'] as $offset => $suffix) {
                $locality = new Locality($governorate, $governorate->getName().' '.$suffix, (string) (1000 + ($index * 100) + $offset));
                $manager->persist($locality);
                $localities[] = $locality;
            }
        }

        return $localities;
    }

    /** @return list<PaymentMethod> */
    private function createPaymentMethods(ObjectManager $manager): array
    {
        $definitions = [
            ['Paiement a la livraison', 'CASH_ON_DELIVERY', PaymentMethodType::CashOnDelivery],
            ['Virement bancaire', 'BANK_TRANSFER', PaymentMethodType::BankTransfer],
            ['Carte bancaire demo', 'CARD_DEMO', PaymentMethodType::CardPayment],
        ];
        $methods = [];

        foreach ($definitions as [$name, $code, $type]) {
            $method = new PaymentMethod($name, $code, $this->faker->sentence(), null, $type);
            $manager->persist($method);
            $methods[] = $method;
        }

        return $methods;
    }

    private function createDeliveryCompany(ObjectManager $manager): DeliveryCompany
    {
        $company = new DeliveryCompany(
            name: 'Demo Delivery',
            slug: 'demo-delivery',
            baseUrl: 'https://delivery.example.test',
            provider: 'generic_http',
            authType: DeliveryAuthType::Basic,
            authConfig: [],
            mappingConfig: [
                'receiver' => '{{customer.full_name}}',
                'phone' => '{{customer.phone|phone}}',
                'address' => '{{address.full_address}}',
                'city' => '{{address.city}}',
                'amount' => '{{order.total}}',
                'reference' => '{{order.number}}',
            ],
            parametersConfig: ['timeout' => 15],
            logoUrl: null,
            description: 'Transporteur fictif pour tester les livraisons.',
        );
        $manager->persist($company);

        $endpoints = [
            [DeliveryEndpointType::Auth, 'Authentification', '/auth/token', 'POST'],
            [DeliveryEndpointType::CreateShipment, 'Création de colis', '/orders', 'POST'],
            [DeliveryEndpointType::TrackShipment, 'Suivi de colis', '/orders/{tracking}', 'GET'],
            [DeliveryEndpointType::CancelShipment, 'Annulation de colis', '/orders/{tracking}/cancel', 'POST'],
        ];
        foreach ($endpoints as [$type, $name, $url, $method]) {
            $manager->persist(new DeliveryEndpoint(
                company: $company,
                type: $type,
                name: $name,
                url: $url,
                httpMethod: \App\Enum\DeliveryHttpMethod::from($method),
            ));
        }

        return $company;
    }

    private function createSubscriptionPlan(ObjectManager $manager): SubscriptionPlan
    {
        $plan = new SubscriptionPlan(
            name: 'Demo Premium',
            description: 'Plan demo complet.',
            durationMonths: 12,
            priceTnd: 499,
            isFree: false,
            isVisible: true,
            isActive: true,
            modules: ['reviews', 'wishlist', 'loyalty', 'coupons', 'promotions', 'blog', 'brands', 'chatbot', 'analytics'],
            chatbotModel: 'llama3.2:1b',
        );
        $manager->persist($plan);

        return $plan;
    }

    /**
     * @param list<PaymentMethod> $paymentMethods
     * @param list<Governorate>   $governorates
     * @param list<Locality>      $localities
     */
    private function createBoutiqueGraph(
        ObjectManager $manager,
        string $name,
        string $slug,
        string $description,
        User $superAdmin,
        SubscriptionPlan $subscriptionPlan,
        array $paymentMethods,
        DeliveryCompany $deliveryCompany,
        Country $country,
        array $governorates,
        array $localities,
    ): void {
        $owner = $this->createUser($manager, null, 'owner.'.$slug.'@hanooti.local', ['ROLE_BOUTIQUE_ADMIN'], $this->faker->firstName(), $this->faker->lastName());
        $boutique = new Boutique($name, $slug);
        $boutique->setOwner($owner);
        $boutique->approve($superAdmin->getUserIdentifier());
        $boutique->publish();
        $boutique->setIsVerified(true);
        $boutique->setIsFeatured(true);
        $boutique->setEmail('contact@'.$slug.'.local');
        $boutique->setPhone($this->faker->phoneNumber());
        $boutique->setDescription($description);
        $boutique->setCoverImage('/images/demo/'.$slug.'/cover.jpg');
        $manager->persist($boutique);

        $owner->addAdministeredBoutique($boutique);
        $manager->persist(new UserShop($owner, $boutique, 'ROLE_BOUTIQUE_ADMIN', UserStatus::Active, (string) $superAdmin->getId()));
        $this->createRoleUsers($manager, $boutique, $slug, $superAdmin);

        $subscription = new Subscription($boutique, PlanType::OneYear, SubscriptionStatus::Pending);
        $subscription->setSubscriptionPlan($subscriptionPlan);
        $subscription->activate($superAdmin->getUserIdentifier());
        $boutique->setCurrentSubscription($subscription);
        $manager->persist($subscription);

        $this->createSettings($manager, $boutique, $slug);
        $this->createChatbotConfig($manager, $boutique);
        $this->createShopPaymentMethods($manager, $boutique, $paymentMethods);
        $this->createDelivery($manager, $boutique, $deliveryCompany);

        $categories = $this->createCategories($manager, $boutique, $slug);
        $brands = $this->createBrands($manager, $boutique, $slug);
        $filters = $this->createFilters($manager, $boutique);
        $products = $this->createProducts($manager, $boutique, $slug, $categories, $brands, $filters);
        $customers = $this->createCustomers($manager, $boutique, $slug, $country, $governorates, $localities);
        $this->createCarts($manager, $boutique, $customers, $products);
        $this->createOrders($manager, $boutique, $customers, $products);
        $this->createMarketing($manager, $boutique);
        $this->createCms($manager, $boutique);
        $this->createMenus($manager, $boutique, $categories);
        $this->createAnnouncements($manager, $boutique);
        $this->createReviews($manager, $boutique, $products);
        $this->createConversations($manager, $boutique, $customers);
        $this->createCustomerNotifications($manager, $customers);
    }

    private function createUser(ObjectManager $manager, ?Boutique $boutique, string $email, array $roles, string $firstName, string $lastName): User
    {
        $user = new User($boutique, $email, $roles, $firstName.' '.$lastName);
        $user->setFirstname($firstName);
        $user->setLastname($lastName);
        $user->setPhone($this->faker->phoneNumber());
        $user->setStatus(UserStatus::Active);
        $user->setPassword('password123');
        $manager->persist($user);

        return $user;
    }

    private function createRoleUsers(ObjectManager $manager, Boutique $boutique, string $slug, User $superAdmin): void
    {
        $roles = [
            ['admin', ['ROLE_BOUTIQUE_ADMIN'], 'ROLE_BOUTIQUE_ADMIN'],
            ['employee', ['ROLE_EMPLOYEE'], 'ROLE_EMPLOYEE'],
            ['caissier', ['ROLE_CAISSIER'], 'ROLE_CAISSIER'],
        ];

        foreach ($roles as [$prefix, $rolesList, $shopRole]) {
            $user = $this->createUser($manager, $boutique, $prefix.'.'.$slug.'@hanooti.local', $rolesList, $this->faker->firstName(), $this->faker->lastName());
            $user->addAdministeredBoutique($boutique);
            $manager->persist(new UserShop($user, $boutique, $shopRole, UserStatus::Active, (string) $superAdmin->getId()));
        }
    }

    private function createSettings(ObjectManager $manager, Boutique $boutique, string $slug): void
    {
        $settings = new BoutiqueSettings(
            $boutique,
            '/images/demo/'.$slug.'/logo.svg',
            '#db2777',
            '#ca8a04',
            $slug.'.localhost',
            'contact@'.$slug.'.local',
            $this->faker->phoneNumber(),
            $this->faker->streetAddress(),
            ['facebook' => $this->faker->url(), 'instagram' => $this->faker->url()],
             'hanooti-marketplace',
        );
        $settings->setSlogan($this->faker->catchPhrase());
        $settings->setDescription($this->faker->paragraph());
        $manager->persist($settings);
    }

    private function createChatbotConfig(ObjectManager $manager, Boutique $boutique): void
    {
        $config = new ChatbotConfig($boutique);
        $config->setIsEnabled(true);
        $config->setModel('llama3.2:1b');
        $config->setSystemPrompt('Tu es assistant de vente. Reponds en francais simple.');
        $manager->persist($config);
    }

    /** @param list<PaymentMethod> $paymentMethods */
    private function createShopPaymentMethods(ObjectManager $manager, Boutique $boutique, array $paymentMethods): void
    {
        foreach ($paymentMethods as $index => $method) {
            $shopMethod = new ShopPaymentMethod($boutique, $method, true, $index + 1);
            $shopMethod->setIsSandbox(true);
            $shopMethod->setGatewayConfig(['provider' => 'fixture', 'mode' => 'sandbox']);
            $manager->persist($shopMethod);
        }
    }

    private function createDelivery(ObjectManager $manager, Boutique $boutique, DeliveryCompany $company): void
    {
        $account = new BoutiqueDeliveryAccount($boutique, $company, 'fixture-login', 'fixture-password', true);
        $account->markAsVerified();
        $manager->persist($account);
        $manager->persist(new DeliveryRule($boutique, 'Livraison standard', DeliveryRuleType::FixedPrice, 7000, null, null, null, null, 0, null, 1, true));
        $manager->persist(new DeliveryRule($boutique, 'Livraison gratuite des 150 TND', DeliveryRuleType::FreeDelivery, 0, null, null, null, null, 150000, null, 2, true));
    }

    /** @return list<Category> */
    private function createCategories(ObjectManager $manager, Boutique $boutique, string $slug): array
    {
        $rootNames = ['Mode', 'Maison', 'Epicerie fine', 'Nouveautes'];
        $categories = [];

        foreach ($rootNames as $index => $name) {
            $category = new Category($boutique, $name, $this->slug($slug.'-'.$name), null, $this->faker->paragraph(), '/images/demo/categories/'.$index.'.jpg');
            $category->setIsFeatured($index < 3);
            $category->setShowInHeader(true);
            $category->setShowOnHomepage(true);
            $category->setMenuPosition($index + 1);
            $manager->persist($category);
            $categories[] = $category;

            if ($index < 2) {
                $child = new Category($boutique, $name.' premium', $this->slug($slug.'-'.$name.'-premium'), $category, $this->faker->sentence());
                $child->setShowCategoryPage(true);
                $manager->persist($child);
                $categories[] = $child;
            }
        }

        return $categories;
    }

    /** @return list<Brand> */
    private function createBrands(ObjectManager $manager, Boutique $boutique, string $slug): array
    {
        $brands = [];
        for ($i = 0; $i < 4; ++$i) {
            $name = $this->faker->company();
            $brand = new Brand($boutique, $name, $this->slug($slug.'-'.$name), '/images/demo/brands/'.$i.'.svg', $this->faker->paragraph(), $this->faker->url());
            $manager->persist($brand);
            $brands[] = $brand;
        }

        return $brands;
    }

    /** @return list<ProductFilter> */
    private function createFilters(ObjectManager $manager, Boutique $boutique): array
    {
        $filters = [
            new ProductFilter($boutique, 'Couleur', 'couleur', 'select'),
            new ProductFilter($boutique, 'Taille', 'taille', 'select'),
            new ProductFilter($boutique, 'Matiere', 'matiere', 'select'),
        ];

        foreach ($filters as $position => $filter) {
            $filter->setPosition($position + 1);
            $manager->persist($filter);
        }

        return $filters;
    }

    /**
     * @param list<Category>      $categories
     * @param list<Brand>         $brands
     * @param list<ProductFilter> $filters
     *
     * @return list<Product>
     */
    private function createProducts(ObjectManager $manager, Boutique $boutique, string $slug, array $categories, array $brands, array $filters): array
    {
        $products = [];

        for ($i = 0; $i < 12; ++$i) {
            $name = ucfirst($this->faker->words(3, true));
            $price = $this->faker->numberBetween(18000, 180000);
            $category = $categories[$i % count($categories)];
            $product = new Product(
                boutique: $boutique,
                name: $name,
                slug: $this->slug($slug.'-'.$name.'-'.$i),
                sku: strtoupper(substr($slug, 0, 3)).'-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                barcode: '619'.str_pad((string) $this->faker->numberBetween(1, 999999999), 9, '0', STR_PAD_LEFT),
                shortDescription: $this->faker->sentence(),
                description: $this->faker->paragraphs(2, true),
                status: ProductStatus::Active,
                costPrice: (int) ($price * 0.55),
                sellingPrice: $price,
                comparePrice: $price + $this->faker->numberBetween(5000, 30000),
                taxRate: 1900,
                weight: $this->faker->numberBetween(100, 3000),
                manageStock: true,
                stockQuantity: $this->faker->numberBetween(5, 150),
                lowStockThreshold: 5,
                isFeatured: 0 === $i % 3,
                isBestSeller: 0 === $i % 4,
                isNew: $i < 4,
                brand: $brands[$i % count($brands)],
                currency: 'TND',
                category: $category,
            );
            $manager->persist($product);
            $manager->persist(new ProductStock($product, $product->getStockQuantity(), 0, 5));
            $manager->persist(new ProductImage($product, '/images/demo/products/'.($i + 1).'.jpg', 1, $name));
            $manager->persist(new ProductProperty($product, 'Origine', 'Tunisie'));
            $manager->persist(new ProductProperty($product, 'Garantie', $this->faker->randomElement(['7 jours', '30 jours', '1 an'])));

            $secondaryCategory = $categories[($i + 1) % count($categories)];
            if ($secondaryCategory !== $category) {
                $manager->persist(new ProductCategory($product, $secondaryCategory));
            }

            foreach ($filters as $filter) {
                $value = match ($filter->getSlug()) {
                    'couleur' => $this->faker->randomElement(['Rose', 'Noir', 'Bleu', 'Or']),
                    'taille' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                    default => $this->faker->randomElement(['Coton', 'Cuir', 'Ceramique', 'Bois']),
                };
                $manager->persist(new ProductFilterValue($filter, $product, $value));
            }

            foreach (['Standard', 'Premium'] as $variantIndex => $variantName) {
                $variant = new ProductVariant($product, $product->getSku().'-V'.($variantIndex + 1), null, $price + ($variantIndex * 10000), $product->getComparePrice(), $this->faker->numberBetween(2, 40), '/images/demo/products/'.($i + 1).'-v'.($variantIndex + 1).'.jpg', 0 === $variantIndex, true);
                $manager->persist($variant);
                $manager->persist(new ProductVariantAttribute($variant, 'Finition', $variantName));
                $manager->persist(new ProductVariantAttribute($variant, 'Couleur', $this->faker->randomElement(['Rose', 'Noir', 'Bleu', 'Or'])));
            }

            $products[] = $product;
        }

        return $products;
    }

    /**
     * @param list<Governorate> $governorates
     * @param list<Locality>    $localities
     *
     * @return list<Customer>
     */
    private function createCustomers(ObjectManager $manager, Boutique $boutique, string $slug, Country $country, array $governorates, array $localities): array
    {
        $customers = [];

        for ($i = 0; $i < 8; ++$i) {
            $firstName = $this->faker->firstName();
            $lastName = $this->faker->lastName();
            $email = 'client'.$i.'.'.$slug.'@example.test';
            $user = null;

            if ($i < 4) {
                $user = $this->createUser($manager, $boutique, 'account.'.$email, ['ROLE_CUSTOMER'], $firstName, $lastName);
            }

            $governorate = $governorates[$i % count($governorates)];
            $locality = $localities[$i % count($localities)];
            $customer = new Customer($boutique, $email, $firstName, $lastName, $this->faker->phoneNumber(), user: $user);
            $customer->setAddressSnapshot(
                $this->faker->streetAddress(),
                $locality->getName(),
                $locality->getPostalCode(),
                $country->getName(),
                (string) $country->getId(),
                $governorate->getName(),
                (string) $governorate->getId(),
                $locality->getName(),
                (string) $locality->getId(),
            );
            $manager->persist($customer);
            $customers[] = $customer;
        }

        return $customers;
    }

    /**
     * @param list<Customer> $customers
     * @param list<Product>  $products
     */
    private function createCarts(ObjectManager $manager, Boutique $boutique, array $customers, array $products): void
    {
        foreach (array_slice($customers, 0, 4) as $index => $customer) {
            $cart = new Cart($boutique, 'fixture-'.$boutique->getSlug().'-'.$index, $customer, CartStatus::Active, 'TND');
            $manager->persist($cart);
            $manager->persist(new CartItem($cart, $products[$index], 1, $products[$index]->getSellingPrice()));
            $manager->persist(new CartItem($cart, $products[$index + 1], 2, $products[$index + 1]->getSellingPrice()));
        }
    }

    /**
     * @param list<Customer> $customers
     * @param list<Product>  $products
     */
    private function createOrders(ObjectManager $manager, Boutique $boutique, array $customers, array $products): void
    {
        foreach (array_slice($customers, 0, 5) as $index => $customer) {
            $product = $products[$index % count($products)];
            $subtotal = $product->getSellingPrice() * 2;
            $order = new Order($boutique, $customer, OrderChannel::Online, [OrderStatus::Pending, OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled][$index], $subtotal, 0, $subtotal + 7000, 'TND');
            $order->setCustomerSnapshot($customer->getFirstName().' '.$customer->getLastName(), $customer->getEmail(), $customer->getPhone(), $customer->getAddress(), $customer->getCity(), $customer->getPostalCode(), $customer->getCountry(), $customer->getCountryId(), $customer->getGovernorate(), $customer->getGovernorateId(), $customer->getLocality(), $customer->getLocalityId());
            $order->setPaymentStatus($index < 1 ? PaymentStatus::Pending : PaymentStatus::Paid);
            $order->setPaymentMethodCode($index % 2 ? 'card_demo' : 'cash_on_delivery');
            $order->addItem($product, $product->getName(), $product->getSku(), 2, $product->getSellingPrice());
            $manager->persist($order);
        }
    }

    private function createMarketing(ObjectManager $manager, Boutique $boutique): void
    {
        $manager->persist(new Promotion($boutique, 'Promotion '.ucfirst($this->faker->word()), PromotionScope::Global, PromotionType::Percentage, 15, new \DateTimeImmutable('-2 days'), $this->faker->sentence(), 100, new \DateTimeImmutable('+30 days')));
        $manager->persist(new Coupon($boutique, 'WELCOME10', 'Bienvenue -10%', CouponType::Percent, CouponScope::Global, 10, 15000, 50000, null, 100, 0, 1, false, true, new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('+60 days')));
    }

    private function createCms(ObjectManager $manager, Boutique $boutique): void
    {
        $home = new CmsPage($boutique, 'Accueil', 'accueil', CmsPageType::Home, CmsPageStatus::Published, $this->faker->sentence(), $this->faker->paragraph(), isHomepage: true, showInHeader: true, sortOrder: 1);
        $about = new CmsPage($boutique, 'A propos', 'a-propos', CmsPageType::About, CmsPageStatus::Published, $this->faker->sentence(), $this->faker->paragraph(), showInFooter: true, sortOrder: 2);
        $manager->persist($home);
        $manager->persist($about);
        $manager->persist(new CmsBlock($home, CmsBlockType::Banner, 'Hero', $this->faker->sentence(), ['button' => 'Acheter'], 1));
        $manager->persist(new CmsBlock($about, CmsBlockType::Text, 'Notre histoire', $this->faker->paragraph(), null, 1));
    }

    /** @param list<Category> $categories */
    private function createMenus(ObjectManager $manager, Boutique $boutique, array $categories): void
    {
        $header = new Menu($boutique, 'Menu principal', Menu::POSITION_HEADER);
        $header->addItem(new MenuItem($header, 'Accueil', 'HOME', '/', null, 1));
        foreach (array_slice($categories, 0, 4) as $index => $category) {
            $header->addItem(new MenuItem($header, $category->getName(), 'CATEGORY', $category->getSlug(), null, $index + 2));
        }
        $footer = new Menu($boutique, 'Menu footer', Menu::POSITION_FOOTER);
        $footer->addItem(new MenuItem($footer, 'A propos', 'PAGE', 'a-propos', null, 1));
        $footer->addItem(new MenuItem($footer, 'Contact', 'CONTACT', '/contact', null, 2));
        $manager->persist($header);
        $manager->persist($footer);
    }

    private function createAnnouncements(ObjectManager $manager, Boutique $boutique): void
    {
        $manager->persist(new Announcement($boutique, 'Livraison offerte des 150 TND.', Announcement::TYPE_TOP_BAR, 'Offre speciale', $this->faker->sentence(), '#831843', '#ffffff'));
    }

    /** @param list<Product> $products */
    private function createReviews(ObjectManager $manager, Boutique $boutique, array $products): void
    {
        foreach (array_slice($products, 0, 6) as $product) {
            $review = new Review($boutique, $product, $this->faker->name(), $this->faker->numberBetween(3, 5), $this->faker->paragraph());
            $review->setTitle($this->faker->sentence(4));
            $review->setVerifiedPurchase(true);
            $review->approve();
            $manager->persist($review);
        }
    }

    /** @param list<Customer> $customers */
    private function createConversations(ObjectManager $manager, Boutique $boutique, array $customers): void
    {
        foreach (array_slice($customers, 0, 3) as $customer) {
            $conversation = new Conversation($boutique);
            $conversation->setGuestName($customer->getFirstName().' '.$customer->getLastName());
            $conversation->setGuestEmail($customer->getEmail());
            $conversation->setGuestPhone($customer->getPhone());
            $manager->persist($conversation);
            $manager->persist(new Message($conversation, 'customer', 'Bonjour, est-ce disponible ?'));
            $manager->persist(new Message($conversation, 'bot', 'Oui, le produit est disponible.'));
        }
    }

    /** @param list<Customer> $customers */
    private function createCustomerNotifications(ObjectManager $manager, array $customers): void
    {
        foreach ($customers as $customer) {
            $manager->persist(new CustomerNotification($customer, 'order_status', 'Commande mise a jour', 'Votre commande est en cours de preparation.'));
        }
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii), '-'));

        return '' !== $slug ? $slug : 'fixture';
    }
}
