<?php

namespace App\Command;

use App\Entity\SubscriptionPlanModule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed:subscription-modules', description: 'Seed default subscription plan modules')]
final class SeedSubscriptionModulesCommand extends Command
{
    private const MODULES = [
        ['code' => 'reviews', 'name' => 'Avis clients', 'description' => 'Gestion des avis et notations', 'category' => 'engagement', 'icon' => 'star', 'isCore' => false],
        ['code' => 'wishlist', 'name' => 'Liste de souhaits', 'description' => 'Liste de souhaits clients', 'category' => 'engagement', 'icon' => 'heart', 'isCore' => false],
        ['code' => 'loyalty', 'name' => 'Programme de fidélité', 'description' => 'Points de fidélité et récompenses', 'category' => 'engagement', 'icon' => 'gift', 'isCore' => false],
        ['code' => 'coupons', 'name' => 'Coupons de réduction', 'description' => 'Codes promo et réductions', 'category' => 'marketing', 'icon' => 'tag', 'isCore' => false],
        ['code' => 'promotions', 'name' => 'Promotions avancées', 'description' => 'Règles de promotion complexes', 'category' => 'marketing', 'icon' => 'percent', 'isCore' => false],
        ['code' => 'blog', 'name' => 'Blog', 'description' => 'Articles de blog et actualités', 'category' => 'contenu', 'icon' => 'newspaper', 'isCore' => false],
        ['code' => 'brands', 'name' => 'Marques', 'description' => 'Gestion des marques', 'category' => 'catalogue', 'icon' => 'copyright', 'isCore' => false],
        ['code' => 'multi_address', 'name' => 'Adresses multiples', 'description' => 'Plusieurs adresses de livraison', 'category' => 'commandes', 'icon' => 'map-pin', 'isCore' => false],
        ['code' => 'chatbot', 'name' => 'Chatbot intelligent', 'description' => 'Assistant virtuel', 'category' => 'support', 'icon' => 'robot', 'isCore' => false],
        ['code' => 'seo_advanced', 'name' => 'SEO avancé', 'description' => 'Optimisation SEO avancée', 'category' => 'marketing', 'icon' => 'search', 'isCore' => false],
        ['code' => 'custom_domain', 'name' => 'Domaine personnalisé', 'description' => 'Nom de domaine propre', 'category' => 'boutique', 'icon' => 'globe', 'isCore' => false],
        ['code' => 'analytics', 'name' => 'Analytics avancés', 'description' => 'Statistiques détaillées', 'category' => 'boutique', 'icon' => 'chart-line', 'isCore' => false],
        ['code' => 'delivery_tracking', 'name' => 'Suivi de livraison', 'description' => 'Suivi des colis en temps réel', 'category' => 'commandes', 'icon' => 'truck', 'isCore' => false],
        ['code' => 'wholesale', 'name' => 'Prix de gros', 'description' => 'Tarification par quantité', 'category' => 'catalogue', 'icon' => 'scale', 'isCore' => false],
        ['code' => 'gift_cards', 'name' => 'Cartes cadeaux', 'description' => 'E-cartes cadeaux', 'category' => 'marketing', 'icon' => 'credit-card', 'isCore' => false],
        ['code' => 'newsletter', 'name' => 'Newsletter', 'description' => 'Campagnes email', 'category' => 'marketing', 'icon' => 'envelope', 'isCore' => false],
        ['code' => 'abandoned_cart', 'name' => 'Panier abandonné', 'description' => 'Récupération de paniers', 'category' => 'marketing', 'icon' => 'cart-plus', 'isCore' => false],
        ['code' => 'order_printing', 'name' => 'Impression de commandes', 'description' => 'Bons de livraison et factures', 'category' => 'commandes', 'icon' => 'print', 'isCore' => false],
        ['code' => 'customer_auth', 'name' => 'Comptes clients', 'description' => 'Connexion et comptes clients de la boutique', 'category' => 'auth', 'icon' => 'user-round', 'isCore' => false],
        ['code' => 'social_login', 'name' => 'Connexion sociale', 'description' => 'Facebook, Google, Apple', 'category' => 'auth', 'icon' => 'user-check', 'isCore' => true],
        ['code' => 'pos', 'name' => 'Point de vente', 'description' => 'Caisse enregistreuse', 'category' => 'boutique', 'icon' => 'cash-register', 'isCore' => false],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->em->getRepository(SubscriptionPlanModule::class);
        $created = 0;
        foreach (self::MODULES as $data) {
            $module = $repository->findOneBy(['code' => $data['code']]);
            if (null === $module) {
                $module = new SubscriptionPlanModule(
                    code: $data['code'],
                    name: $data['name'],
                    description: $data['description'],
                    category: $data['category'],
                    icon: $data['icon'],
                    isCore: $data['isCore'],
                );
                $this->em->persist($module);
                ++$created;
            }
        }

        $this->em->flush();

        $output->writeln(sprintf('Subscription plan modules synchronized (%d created).', $created));

        return Command::SUCCESS;
    }
}
