<?php

namespace App\Command;

use App\Entity\SubscriptionPlan;
use App\Entity\SubscriptionModule;
use App\Entity\SubscriptionPlanModule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed:subscription-plans', description: 'Seed default subscription plans')]
final class SeedSubscriptionPlansCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $existing = $this->em->getRepository(SubscriptionPlan::class)->findAll();
        if (count($existing) > 0) {
            $output->writeln('Plans already seeded ('.count($existing).' found).');

            return Command::SUCCESS;
        }

        $allModuleCodes = ['reviews', 'wishlist', 'customer_auth', 'loyalty', 'coupons', 'promotions', 'blog', 'brands', 'multi_address', 'chatbot', 'seo_advanced', 'custom_domain', 'analytics', 'delivery_tracking', 'wholesale', 'gift_cards', 'newsletter', 'abandoned_cart', 'order_printing', 'social_login', 'pos'];

        $basicAllowed = ['reviews', 'wishlist', 'customer_auth', 'brands', 'social_login'];
        $businessAllowed = ['reviews', 'wishlist', 'customer_auth', 'brands', 'social_login', 'coupons', 'promotions', 'multi_address', 'delivery_tracking', 'blog', 'analytics', 'newsletter', 'abandoned_cart', 'order_printing'];

        $plans = [
            [
                'name' => 'Starter',
                'description' => 'Plan gratuit pour découvrir la plateforme. Idéal pour les débutants.',
                'durationMonths' => 0,
                'priceTnd' => 0,
                'isFree' => true,
                'isVisible' => true,
                'allowedModules' => $basicAllowed,
            ],
            [
                'name' => 'Business 3 mois',
                'description' => 'Solution complète pour développer votre activité en ligne.',
                'durationMonths' => 3,
                'priceTnd' => 9900,
                'isFree' => false,
                'isVisible' => true,
                'allowedModules' => $businessAllowed,
            ],
            [
                'name' => 'Business 6 mois',
                'description' => 'Économisez avec l\'engagement 6 mois.',
                'durationMonths' => 6,
                'priceTnd' => 17900,
                'isFree' => false,
                'isVisible' => true,
                'allowedModules' => $businessAllowed,
            ],
            [
                'name' => 'Business 12 mois',
                'description' => 'La meilleure offre pour une visibilité annuelle.',
                'durationMonths' => 12,
                'priceTnd' => 29900,
                'isFree' => false,
                'isVisible' => true,
                'allowedModules' => $businessAllowed,
            ],
            [
                'name' => 'Premium 12 mois',
                'description' => 'Tous les modules débloqués. L\'expérience complète.',
                'durationMonths' => 12,
                'priceTnd' => 49900,
                'isFree' => false,
                'isVisible' => true,
                'allowedModules' => null,
            ],
        ];

        $allModules = $this->em->getRepository(SubscriptionPlanModule::class)->findAll();
        $moduleMap = [];
        foreach ($allModules as $m) {
            $moduleMap[$m->getCode()] = $m;
        }

        foreach ($plans as $data) {
            $plan = new SubscriptionPlan(
                name: $data['name'],
                description: $data['description'],
                durationMonths: $data['durationMonths'],
                priceTnd: $data['priceTnd'],
                isFree: $data['isFree'],
                isVisible: $data['isVisible'],
                isActive: true,
                modules: null,
            );
            $this->em->persist($plan);

            $allowed = $data['allowedModules'] ?? $allModuleCodes;
            foreach ($allowed as $code) {
                if (!isset($moduleMap[$code])) {
                    continue;
                }
                $sm = new SubscriptionModule(
                    plan: $plan,
                    module: $moduleMap[$code],
                    isAllowed: true,
                );
                $this->em->persist($sm);
            }

            $output->writeln(sprintf('  Created plan "%s" with %d modules.', $data['name'], count($allowed)));
        }

        $this->em->flush();

        $output->writeln(sprintf('Seeded %d subscription plans.', count($plans)));

        return Command::SUCCESS;
    }
}
