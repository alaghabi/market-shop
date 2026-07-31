<?php

namespace App\Command;

use App\Entity\PlanQuota;
use App\Entity\QuotaDefinition;
use App\Entity\SubscriptionPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed:quota-definitions', description: 'Seed default quota definitions and base plan limits')]
final class SeedQuotaDefinitionsCommand extends Command
{
    private const QUOTAS = [
        ['code' => 'max_products', 'name' => 'Produits', 'unit' => 'produits', 'category' => 'catalogue', 'icon' => 'box'],
        ['code' => 'max_categories', 'name' => 'Categories', 'unit' => 'categories', 'category' => 'catalogue', 'icon' => 'folder'],
        ['code' => 'max_employees', 'name' => 'Employes', 'unit' => 'employes', 'category' => 'equipe', 'icon' => 'users'],
        ['code' => 'max_admins', 'name' => 'Administrateurs', 'unit' => 'administrateurs', 'category' => 'equipe', 'icon' => 'user-shield'],
        ['code' => 'max_customers', 'name' => 'Clients', 'unit' => 'clients', 'category' => 'clients', 'icon' => 'user'],
        ['code' => 'max_brands', 'name' => 'Marques', 'unit' => 'marques', 'category' => 'catalogue', 'icon' => 'copyright'],
        ['code' => 'max_attributes', 'name' => 'Attributs', 'unit' => 'attributs', 'category' => 'catalogue', 'icon' => 'sliders'],
        ['code' => 'max_images', 'name' => 'Images', 'unit' => 'images', 'category' => 'medias', 'icon' => 'image'],
        ['code' => 'max_videos', 'name' => 'Videos', 'unit' => 'videos', 'category' => 'medias', 'icon' => 'video'],
        ['code' => 'disk_space_mb', 'name' => 'Espace disque', 'unit' => 'Mo', 'category' => 'medias', 'icon' => 'database'],
        ['code' => 'max_domains', 'name' => 'Domaines personnalises', 'unit' => 'domaines', 'category' => 'boutique', 'icon' => 'globe'],
        ['code' => 'max_subdomains', 'name' => 'Sous-domaines', 'unit' => 'sous-domaines', 'category' => 'boutique', 'icon' => 'sitemap'],
        ['code' => 'max_boutiques', 'name' => 'Boutiques', 'unit' => 'boutiques', 'category' => 'boutique', 'icon' => 'store'],
        ['code' => 'max_marketing_campaigns', 'name' => 'Campagnes marketing', 'unit' => 'campagnes', 'category' => 'marketing', 'icon' => 'bullhorn'],
    ];

    /**
     * Base limits per plan name, matching SeedSubscriptionPlansCommand. Null means unlimited.
     */
    private const PLAN_LIMITS = [
        'Starter' => ['max_products' => 30, 'max_categories' => 5, 'max_employees' => 1, 'max_admins' => 1, 'max_customers' => 200, 'max_brands' => 5, 'max_attributes' => 10, 'max_images' => 100, 'max_videos' => 0, 'disk_space_mb' => 500, 'max_domains' => 0, 'max_subdomains' => 1, 'max_boutiques' => 1, 'max_marketing_campaigns' => 0],
        'Business 1 mois' => ['max_products' => 300, 'max_categories' => 30, 'max_employees' => 5, 'max_admins' => 2, 'max_customers' => 5000, 'max_brands' => 30, 'max_attributes' => 50, 'max_images' => 2000, 'max_videos' => 20, 'disk_space_mb' => 5000, 'max_domains' => 1, 'max_subdomains' => 3, 'max_boutiques' => 2, 'max_marketing_campaigns' => 10],
        'Business 3 mois' => ['max_products' => 300, 'max_categories' => 30, 'max_employees' => 5, 'max_admins' => 2, 'max_customers' => 5000, 'max_brands' => 30, 'max_attributes' => 50, 'max_images' => 2000, 'max_videos' => 20, 'disk_space_mb' => 5000, 'max_domains' => 1, 'max_subdomains' => 3, 'max_boutiques' => 2, 'max_marketing_campaigns' => 10],
        'Business 6 mois' => ['max_products' => 300, 'max_categories' => 30, 'max_employees' => 5, 'max_admins' => 2, 'max_customers' => 5000, 'max_brands' => 30, 'max_attributes' => 50, 'max_images' => 2000, 'max_videos' => 20, 'disk_space_mb' => 5000, 'max_domains' => 1, 'max_subdomains' => 3, 'max_boutiques' => 2, 'max_marketing_campaigns' => 10],
        'Business 12 mois' => ['max_products' => 300, 'max_categories' => 30, 'max_employees' => 5, 'max_admins' => 2, 'max_customers' => 5000, 'max_brands' => 30, 'max_attributes' => 50, 'max_images' => 2000, 'max_videos' => 20, 'disk_space_mb' => 5000, 'max_domains' => 1, 'max_subdomains' => 3, 'max_boutiques' => 2, 'max_marketing_campaigns' => 10],
        // Explicit null = unlimited (missing key ≠ unlimited in AccountSubscriptionService / SubscriptionManager).
        'Premium 12 mois' => [
            'max_products' => null,
            'max_categories' => null,
            'max_employees' => null,
            'max_admins' => null,
            'max_customers' => null,
            'max_brands' => null,
            'max_attributes' => null,
            'max_images' => null,
            'max_videos' => null,
            'disk_space_mb' => null,
            'max_domains' => null,
            'max_subdomains' => null,
            'max_boutiques' => null,
            'max_marketing_campaigns' => null,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $quotaRepository = $this->em->getRepository(QuotaDefinition::class);
        $planQuotaRepository = $this->em->getRepository(PlanQuota::class);

        $quotaMap = [];
        foreach ($quotaRepository->findAll() as $quota) {
            $quotaMap[$quota->getCode()] = $quota;
        }

        $addedQuotas = 0;
        foreach (self::QUOTAS as $data) {
            if (isset($quotaMap[$data['code']])) {
                continue;
            }

            $quota = new QuotaDefinition(
                code: $data['code'],
                name: $data['name'],
                unit: $data['unit'],
                category: $data['category'],
                icon: $data['icon'],
            );
            $this->em->persist($quota);
            $quotaMap[$data['code']] = $quota;
            ++$addedQuotas;
        }

        $addedLimits = 0;
        $plans = $this->em->getRepository(SubscriptionPlan::class)->findAll();
        foreach ($plans as $plan) {
            $limits = self::PLAN_LIMITS[$plan->getName()] ?? null;
            if (null === $limits) {
                continue;
            }

            foreach ($limits as $code => $limitValue) {
                if (!isset($quotaMap[$code])) {
                    continue;
                }
                if (null !== $planQuotaRepository->findOneByPlanAndQuota($plan, $quotaMap[$code])) {
                    continue;
                }
                $this->em->persist(new PlanQuota(plan: $plan, quota: $quotaMap[$code], limitValue: $limitValue));
                ++$addedLimits;
            }
        }

        $this->em->flush();

        $output->writeln(sprintf('Seeded %d new quota definitions and %d new plan limits.', $addedQuotas, $addedLimits));

        return Command::SUCCESS;
    }
}
