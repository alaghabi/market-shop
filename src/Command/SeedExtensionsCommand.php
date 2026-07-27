<?php

namespace App\Command;

use App\Entity\Extension;
use App\Enum\ExtensionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed:extensions', description: 'Seed a sample extensions catalogue (quota boosts, modules, themes, services)')]
final class SeedExtensionsCommand extends Command
{
    private const EXTENSIONS = [
        ['code' => 'boost_products_100', 'name' => '+100 produits', 'description' => 'Augmente votre quota de produits de 100 unites.', 'type' => ExtensionType::QuotaBoost, 'targetCode' => 'max_products', 'value' => 100, 'priceTnd' => 3000, 'durationMonths' => null, 'requiresValidation' => false, 'icon' => 'box'],
        ['code' => 'boost_categories_10', 'name' => '+10 categories', 'description' => 'Augmente votre quota de categories de 10 unites.', 'type' => ExtensionType::QuotaBoost, 'targetCode' => 'max_categories', 'value' => 10, 'priceTnd' => 1500, 'durationMonths' => null, 'requiresValidation' => false, 'icon' => 'folder'],
        ['code' => 'boost_employees_5', 'name' => '+5 employes', 'description' => 'Augmente votre quota d\'employes de 5 unites.', 'type' => ExtensionType::QuotaBoost, 'targetCode' => 'max_employees', 'value' => 5, 'priceTnd' => 2000, 'durationMonths' => 12, 'requiresValidation' => true, 'icon' => 'users'],
        ['code' => 'boost_storage_20gb', 'name' => '+20 Go de stockage', 'description' => 'Augmente votre espace disque de 20 Go.', 'type' => ExtensionType::QuotaBoost, 'targetCode' => 'disk_space_mb', 'value' => 20000, 'priceTnd' => 2500, 'durationMonths' => 12, 'requiresValidation' => false, 'icon' => 'database'],
        ['code' => 'module_loyalty_premium', 'name' => 'Wallet Premium', 'description' => 'Active le module Wallet avance pour votre boutique.', 'type' => ExtensionType::Module, 'targetCode' => 'loyalty', 'value' => null, 'priceTnd' => 5000, 'durationMonths' => 12, 'requiresValidation' => true, 'icon' => 'wallet'],
        ['code' => 'module_wishlist_premium', 'name' => 'Favoris Premium', 'description' => 'Active la liste de souhaits pour votre boutique.', 'type' => ExtensionType::Module, 'targetCode' => 'wishlist', 'value' => null, 'priceTnd' => 3000, 'durationMonths' => 12, 'requiresValidation' => true, 'icon' => 'heart'],
        ['code' => 'module_customer_auth_premium', 'name' => 'Comptes clients Premium', 'description' => 'Active la connexion et les comptes clients pour votre boutique.', 'type' => ExtensionType::Module, 'targetCode' => 'customer_auth', 'value' => null, 'priceTnd' => 3000, 'durationMonths' => 12, 'requiresValidation' => true, 'icon' => 'user-round'],
        ['code' => 'module_analytics_premium', 'name' => 'Analytics Premium', 'description' => 'Statistiques avancees et rapports detailles.', 'type' => ExtensionType::Module, 'targetCode' => 'analytics', 'value' => null, 'priceTnd' => 4000, 'durationMonths' => 12, 'requiresValidation' => true, 'icon' => 'chart-line'],
        ['code' => 'meta_pixel', 'name' => 'Meta Pixel', 'description' => 'Suivi des conversions et audiences Meta pour votre boutique.', 'type' => ExtensionType::Service, 'targetCode' => 'meta_pixel', 'value' => null, 'priceTnd' => 1500, 'durationMonths' => 12, 'requiresValidation' => false, 'icon' => 'facebook'],
        ['code' => 'module_blog', 'name' => 'Blog', 'description' => 'Active le module blog pour publier des articles.', 'type' => ExtensionType::Module, 'targetCode' => 'blog', 'value' => null, 'priceTnd' => 0, 'durationMonths' => null, 'requiresValidation' => false, 'icon' => 'newspaper'],
        ['code' => 'theme_fashion', 'name' => 'Theme Fashion', 'description' => 'Theme premium oriente mode et pret-a-porter.', 'type' => ExtensionType::Theme, 'targetCode' => 'fashion', 'value' => null, 'priceTnd' => 6000, 'durationMonths' => null, 'requiresValidation' => true, 'icon' => 'tshirt'],
        ['code' => 'theme_luxury', 'name' => 'Theme Luxury', 'description' => 'Theme premium haut de gamme pour boutiques de luxe.', 'type' => ExtensionType::Theme, 'targetCode' => 'luxury', 'value' => null, 'priceTnd' => 9000, 'durationMonths' => null, 'requiresValidation' => true, 'icon' => 'gem'],
        ['code' => 'theme_electronics', 'name' => 'Theme Electronics', 'description' => 'Theme premium adapte a l\'electronique et high-tech.', 'type' => ExtensionType::Theme, 'targetCode' => 'electronics', 'value' => null, 'priceTnd' => 6000, 'durationMonths' => null, 'requiresValidation' => true, 'icon' => 'microchip'],
        ['code' => 'service_custom_domain', 'name' => 'Domaine personnalise', 'description' => 'Connectez votre propre nom de domaine a votre boutique.', 'type' => ExtensionType::Service, 'targetCode' => 'custom_domain', 'value' => null, 'priceTnd' => 4000, 'durationMonths' => 12, 'requiresValidation' => true, 'icon' => 'globe'],
        ['code' => 'service_priority_support', 'name' => 'Support Premium', 'description' => 'Assistance prioritaire avec un temps de reponse garanti.', 'type' => ExtensionType::Service, 'targetCode' => null, 'value' => null, 'priceTnd' => 2000, 'durationMonths' => 12, 'requiresValidation' => true, 'icon' => 'headset'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->em->getRepository(Extension::class);
        $seeded = 0;

        foreach (self::EXTENSIONS as $data) {
            if (null !== $repository->findOneByCode($data['code'])) {
                continue;
            }

            $extension = new Extension(
                code: $data['code'],
                name: $data['name'],
                description: $data['description'],
                type: $data['type'],
                targetCode: $data['targetCode'],
                value: $data['value'],
                priceTnd: $data['priceTnd'],
                durationMonths: $data['durationMonths'],
                requiresValidation: $data['requiresValidation'],
                icon: $data['icon'],
            );
            $this->em->persist($extension);
            ++$seeded;
        }

        $this->em->flush();

        $output->writeln(sprintf('Seeded %d new extensions.', $seeded));

        return Command::SUCCESS;
    }
}
