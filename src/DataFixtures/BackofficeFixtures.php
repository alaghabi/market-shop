<?php

namespace App\DataFixtures;

use App\Entity\AuditLog;
use App\Entity\Boutique;
use App\Entity\BoutiqueDeliveryAccount;
use App\Entity\Customer;
use App\Entity\CustomerLoyalty;
use App\Entity\DeliveryApiLog;
use App\Entity\DeliveryCompany;
use App\Entity\Extension;
use App\Entity\ExtensionRequest;
use App\Entity\Invoice;
use App\Entity\LoyaltyProgram;
use App\Entity\LoyaltyReward;
use App\Entity\LoyaltyRule;
use App\Entity\LoyaltyTransaction;
use App\Entity\Notification;
use App\Entity\NotificationTemplate;
use App\Entity\Order;
use App\Entity\PlatformModule;
use App\Entity\PlanQuota;
use App\Entity\QuotaDefinition;
use App\Entity\Refund;
use App\Entity\RefundItem;
use App\Entity\Shipment;
use App\Entity\ShopModule;
use App\Entity\SubscriptionModule;
use App\Entity\SubscriptionPlan;
use App\Entity\SubscriptionPlanModule;
use App\Entity\Webhook;
use App\Enum\DeliveryEndpointType;
use App\Enum\ExtensionType;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\LoyaltyCostType;
use App\Enum\LoyaltyTransactionType;
use App\Enum\LoyaltyValidityPolicy;
use App\Enum\NotificationChannel;
use App\Enum\RefundStatus;
use App\Enum\RefundType;
use App\Enum\ShipmentStatus;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class BackofficeFixtures extends Fixture implements DependentFixtureInterface
{
    private const MODULES = [
        ['reviews', 'Avis clients', 'engagement', 'star', false],
        ['wishlist', 'Liste de souhaits', 'engagement', 'heart', false],
        ['loyalty', 'Programme de fidelite', 'engagement', 'gift', false],
        ['coupons', 'Coupons de reduction', 'marketing', 'tag', false],
        ['promotions', 'Promotions avancees', 'marketing', 'percent', false],
        ['blog', 'Blog', 'contenu', 'newspaper', false],
        ['brands', 'Marques', 'catalogue', 'copyright', false],
        ['customer_auth', 'Comptes clients', 'auth', 'user-round', false],
        ['multi_address', 'Adresses multiples', 'commandes', 'map-pin', false],
        ['chatbot', 'Chatbot intelligent', 'support', 'robot', false],
        ['seo_advanced', 'SEO avance', 'marketing', 'search', false],
        ['custom_domain', 'Domaine personnalise', 'boutique', 'globe', false],
        ['analytics', 'Analytics avances', 'boutique', 'chart-line', false],
        ['delivery_tracking', 'Suivi de livraison', 'commandes', 'truck', false],
        ['wholesale', 'Prix de gros', 'catalogue', 'scale', false],
        ['gift_cards', 'Cartes cadeaux', 'marketing', 'credit-card', false],
        ['newsletter', 'Newsletter', 'marketing', 'envelope', false],
        ['abandoned_cart', 'Panier abandonne', 'marketing', 'cart-plus', false],
        ['order_printing', 'Impression de commandes', 'commandes', 'print', false],
        ['social_login', 'Connexion sociale', 'auth', 'user-check', true],
        ['pos', 'Point de vente', 'boutique', 'cash-register', false],
    ];

    private const QUOTAS = [
        ['max_products', 'Produits', 'produits', 'catalogue', 'box'],
        ['max_categories', 'Categories', 'categories', 'catalogue', 'folder'],
        ['max_employees', 'Employes', 'employes', 'equipe', 'users'],
        ['max_customers', 'Clients', 'clients', 'clients', 'user'],
        ['max_brands', 'Marques', 'marques', 'catalogue', 'copyright'],
        ['disk_space_mb', 'Espace disque', 'Mo', 'medias', 'database'],
    ];

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $boutiques = $manager->getRepository(Boutique::class)->findAll();
        $plans = $manager->getRepository(SubscriptionPlan::class)->findAll();
        $modules = $this->ensureModules($manager);

        $this->createModuleAccess($manager, $boutiques, $plans, $modules);
        $this->createQuotas($manager, $plans);
        $extensions = $this->createExtensions($manager);

        foreach ($boutiques as $boutique) {
            $customers = $manager->getRepository(Customer::class)->findBy(['boutique' => $boutique], [], 3);
            $orders = $manager->getRepository(Order::class)->findBy(['boutique' => $boutique], ['createdAt' => 'DESC'], 3);
            $this->createLoyalty($manager, $boutique, $customers, $orders);
            $this->createOperationalData($manager, $boutique, $orders);
            $this->createExtensionRequest($manager, $boutique, $extensions);
            $this->createNotifications($manager, $boutique);
            $this->createNotificationTemplate($manager, $boutique);
            $this->createWebhook($manager, $boutique);
            $this->createAuditLogs($manager, $boutique);
        }

        $manager->flush();
    }

    /** @return array<string, SubscriptionPlanModule> */
    private function ensureModules(ObjectManager $manager): array
    {
        $repository = $manager->getRepository(SubscriptionPlanModule::class);
        $modules = [];

        foreach (self::MODULES as [$code, $name, $category, $icon, $isCore]) {
            $module = $repository->findOneBy(['code' => $code]);
            if (!$module instanceof SubscriptionPlanModule) {
                $module = new SubscriptionPlanModule($code, $name, 'Module de demonstration '.$name, $category, $icon, $isCore);
                $manager->persist($module);
            }
            $modules[$code] = $module;
        }

        return $modules;
    }

    /** @param list<Boutique> $boutiques @param list<SubscriptionPlan> $plans @param array<string, SubscriptionPlanModule> $modules */
    private function createModuleAccess(ObjectManager $manager, array $boutiques, array $plans, array $modules): void
    {
        $platformRepository = $manager->getRepository(PlatformModule::class);
        $shopRepository = $manager->getRepository(ShopModule::class);
        $subscriptionRepository = $manager->getRepository(SubscriptionModule::class);

        foreach ($modules as $module) {
            if (!$platformRepository->findOneBy(['module' => $module])) {
                $manager->persist(new PlatformModule($module, true));
            }
            foreach ($boutiques as $boutique) {
                if (!$shopRepository->findOneBy(['boutique' => $boutique, 'module' => $module])) {
                    $manager->persist(new ShopModule($boutique, $module, true));
                }
            }
            foreach ($plans as $plan) {
                if (!$subscriptionRepository->findOneBy(['plan' => $plan, 'module' => $module])) {
                    $planModules = $plan->getModules() ?? [];
                    $allowed = [] === $planModules || in_array($module->getCode(), $planModules, true);
                    $manager->persist(new SubscriptionModule($plan, $module, $allowed));
                }
            }
        }
    }

    /** @param list<SubscriptionPlan> $plans */
    private function createQuotas(ObjectManager $manager, array $plans): void
    {
        $quotaRepository = $manager->getRepository(QuotaDefinition::class);
        $planQuotaRepository = $manager->getRepository(PlanQuota::class);

        foreach (self::QUOTAS as [$code, $name, $unit, $category, $icon]) {
            $quota = $quotaRepository->findOneBy(['code' => $code]);
            if (!$quota instanceof QuotaDefinition) {
                $quota = new QuotaDefinition($code, $name, 'Quota de demonstration', $unit, $category, $icon);
                $manager->persist($quota);
            }
            foreach ($plans as $plan) {
                if (!$planQuotaRepository->findOneBy(['plan' => $plan, 'quota' => $quota])) {
                    $manager->persist(new PlanQuota($plan, $quota, 100));
                }
            }
        }
    }

    /** @return list<Extension> */
    private function createExtensions(ObjectManager $manager): array
    {
        $repository = $manager->getRepository(Extension::class);
        $definitions = [
            ['demo_loyalty', 'Loyalty Premium', ExtensionType::Module, 'loyalty', 49],
            ['demo_storage', 'Stockage supplementaire', ExtensionType::QuotaBoost, 'disk_space_mb', 25],
            ['demo_domain', 'Domaine personnalise', ExtensionType::Service, 'custom_domain', 39],
        ];
        $extensions = [];

        foreach ($definitions as [$code, $name, $type, $target, $price]) {
            $extension = $repository->findOneBy(['code' => $code]);
            if (!$extension instanceof Extension) {
                $extension = new Extension($code, $name, 'Extension de demonstration', $type, $target, null, $price, 12, false, true, 'puzzle');
                $manager->persist($extension);
            }
            $extensions[] = $extension;
        }

        return $extensions;
    }

    /** @param list<Customer> $customers @param list<Order> $orders */
    private function createLoyalty(ObjectManager $manager, Boutique $boutique, array $customers, array $orders): void
    {
        $programRepository = $manager->getRepository(LoyaltyProgram::class);
        if ($programRepository->findOneBy(['boutique' => $boutique])) {
            return;
        }

        $program = new LoyaltyProgram($boutique, true, LoyaltyValidityPolicy::Days365, null, true, true, true, 100, 1, 5000, 1000, 0, 0, true, true, true, true, true, true);
        $reward = new LoyaltyReward($program, 'Bon de reduction 5 TND', 'Recompense de demonstration', 'fixed_discount', ['discountCents' => 500], LoyaltyCostType::Points, 500, 5000, 5000, null, true, true, true, true, 100, 1, 1, true);
        $rule = new LoyaltyRule($program, 'Points sur commande', 'Regle de demonstration', 'order_amount', ['amountCents' => 10000], 100, false, 1.0, null, $reward, 1, true, true);
        $manager->persist($program);
        $manager->persist($reward);
        $manager->persist($rule);

        $customer = $customers[0] ?? null;
        $order = $orders[0] ?? null;
        if ($customer instanceof Customer) {
            $account = new CustomerLoyalty($customer, $boutique, 850, 1000, 150);
            $manager->persist($account);
            $manager->persist(new LoyaltyTransaction($account, $boutique, LoyaltyTransactionType::Earn, 1000, 850, null, $order, $rule, null, 'Commande de demonstration'));
            $manager->persist(new LoyaltyTransaction($account, $boutique, LoyaltyTransactionType::Redeem, -150, null, 1500, $order, null, $reward, 'Recompense de demonstration'));
        }
    }

    /** @param list<Order> $orders */
    private function createOperationalData(ObjectManager $manager, Boutique $boutique, array $orders): void
    {
        $company = $manager->getRepository(DeliveryCompany::class)->findOneBy([]);
        $account = $manager->getRepository(BoutiqueDeliveryAccount::class)->findOneBy(['boutique' => $boutique]);
        if (!$company instanceof DeliveryCompany || !$orders || !$account instanceof BoutiqueDeliveryAccount) {
            return;
        }

        $order = $orders[0];
        $shipment = new Shipment($boutique, $order, $company, $account, ShipmentStatus::InTransit, 'DEMO-'.strtoupper(substr((string) $order->getId(), 0, 8)), null, 7000, ['orderId' => (string) $order->getId()], ['status' => 'in_transit']);
        $manager->persist($shipment);
        $manager->persist(new DeliveryApiLog($company, $boutique, DeliveryEndpointType::CreateShipment, 'POST', 'https://delivery.example.test/orders', ['orderId' => (string) $order->getId()], 200, ['success' => true], true, null, 120));

        $customer = $order->getCustomer();
        if (!$manager->getRepository(Invoice::class)->findOneBy(['order' => $order])) {
            $manager->persist(new Invoice(
                invoiceNumber: 'FAC-DEMO-'.strtoupper(substr($boutique->getSlug(), 0, 10)).'-'.strtoupper(substr((string) $order->getId(), 0, 8)),
                boutique: $boutique,
                customer: $customer,
                order: $order,
                subscription: null,
                type: InvoiceType::Order,
                status: InvoiceStatus::Paid,
                currency: $order->getCurrency(),
                subtotal: $order->getSubtotalCents(),
                shippingTotal: $order->getTotalCents() - $order->getSubtotalCents(),
                total: $order->getTotalCents(),
                boutiqueName: $boutique->getName(),
                boutiqueEmail: $boutique->getEmail(),
                boutiquePhone: $boutique->getPhone(),
                customerName: $order->getCustomerName(),
                customerEmail: $order->getCustomerEmail(),
                paidAt: new \DateTimeImmutable(),
            ));
        }

        if (!$manager->getRepository(Refund::class)->findOneBy(['order' => $order])) {
            $refund = new Refund('REM-DEMO-'.strtoupper(substr($boutique->getSlug(), 0, 10)).'-'.strtoupper(substr((string) $order->getId(), 0, 8)), $boutique, $order, $customer, RefundType::Partial, RefundStatus::Pending, $order->getCurrency(), 5000, 0, 5000, 'Retour de demonstration');
            $item = $order->getItems()->first();
            if ($item) {
                $refund->addItem(new RefundItem($refund, $item, $item->getProductName(), 1, $item->getUnitPriceCents(), $item->getUnitPriceCents()));
            }
            $manager->persist($refund);
        }
    }

    /** @param list<Extension> $extensions */
    private function createExtensionRequest(ObjectManager $manager, Boutique $boutique, array $extensions): void
    {
        $extension = $extensions[0] ?? null;
        if (!$extension instanceof Extension || $manager->getRepository(ExtensionRequest::class)->findOneBy(['boutique' => $boutique, 'extension' => $extension])) {
            return;
        }

        $request = new ExtensionRequest($boutique, $extension, $extension->getPriceTnd());
        $request->initializeWorkflow();
        $manager->persist($request);
    }

    private function createNotifications(ObjectManager $manager, Boutique $boutique): void
    {
        if ($manager->getRepository(Notification::class)->findOneBy(['boutique' => $boutique])) {
            return;
        }
        $manager->persist(new Notification(null, 'order', 'Nouvelle commande', 'Une commande de demonstration est disponible.', $boutique));
        $manager->persist(new Notification(null, 'system', 'Abonnement actif', 'Votre abonnement de demonstration est actif.', $boutique, true));
    }

    private function createNotificationTemplate(ObjectManager $manager, Boutique $boutique): void
    {
        if ($manager->getRepository(NotificationTemplate::class)->findOneBy(['boutique' => $boutique, 'eventCode' => 'order.created', 'channel' => NotificationChannel::Email])) {
            return;
        }
        $manager->persist(new NotificationTemplate($boutique, 'order.created', NotificationChannel::Email, 'Nouvelle commande {{order.id}}', 'Votre commande est en preparation.', true));
    }

    private function createWebhook(ObjectManager $manager, Boutique $boutique): void
    {
        if ($manager->getRepository(Webhook::class)->findOneBy(['boutique' => $boutique])) {
            return;
        }
        $manager->persist(new Webhook($boutique, 'https://webhook.example.test/hanooti', ['order.created', 'order.updated'], 'demo-secret', 'ACTIVE'));
    }

    private function createAuditLogs(ObjectManager $manager, Boutique $boutique): void
    {
        if ($manager->getRepository(AuditLog::class)->findOneBy(['boutique' => $boutique])) {
            return;
        }
        $manager->persist(new AuditLog('owner@'.$boutique->getSlug().'.local', 'ROLE_BOUTIQUE_ADMIN', $boutique, 'fixture.load', 'Boutique', (string) $boutique->getId(), ['source' => 'demo-fixture'], '127.0.0.1'));
        $manager->persist(new AuditLog('super-admin.fixture@hanooti.local', 'ROLE_SUPER_ADMIN', $boutique, 'subscription.activate', 'Subscription', null, ['source' => 'demo-fixture'], '127.0.0.1'));
    }
}
