<?php

namespace App\Command;

use App\Entity\DeliveryCompany;
use App\Entity\DeliveryEndpoint;
use App\Enum\DeliveryAuthType;
use App\Enum\DeliveryEndpointType;
use App\Enum\DeliveryHttpMethod;
use App\Enum\DeliveryResponseType;
use App\Repository\DeliveryCompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:tunisian-delivery-companies', description: 'Seed First Delivery, Navex and Intigo delivery companies')]
final class SeedTunisianDeliveryCompaniesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeliveryCompanyRepository $companies,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->definitions() as $definition) {
            $company = $this->upsertCompany($definition);
            $io->writeln(sprintf('✓ %s (%s)', $company->getName(), $company->getSlug()));
        }

        $this->em->flush();
        $io->success('Tunisian delivery companies seeded.');

        return Command::SUCCESS;
    }

    /**
     * @param array{
     *   name: string,
     *   slug: string,
     *   baseUrl: string,
     *   provider: string,
     *   authType: DeliveryAuthType,
     *   authConfig: array<string, mixed>,
     *   mappingConfig: array<string, mixed>,
     *   parametersConfig: array<string, mixed>,
     *   description: string,
     *   endpoints: list<array{type: DeliveryEndpointType, name: string, url: string, method: DeliveryHttpMethod}>
     * } $definition
     */
    private function upsertCompany(array $definition): DeliveryCompany
    {
        $company = $this->companies->findOneBySlug($definition['slug']);
        if (!$company instanceof DeliveryCompany) {
            $company = new DeliveryCompany(
                name: $definition['name'],
                slug: $definition['slug'],
                baseUrl: $definition['baseUrl'],
                provider: $definition['provider'],
                authType: $definition['authType'],
                authConfig: $definition['authConfig'],
                mappingConfig: $definition['mappingConfig'],
                parametersConfig: $definition['parametersConfig'],
                description: $definition['description'],
            );
            $this->em->persist($company);
        } else {
            $company->setName($definition['name']);
            $company->setBaseUrl($definition['baseUrl']);
            $company->setProvider($definition['provider']);
            $company->setAuthType($definition['authType']);
            $company->setAuthConfig($definition['authConfig']);
            $company->setMappingConfig($definition['mappingConfig']);
            $company->setParametersConfig($definition['parametersConfig']);
            $company->setDescription($definition['description']);
            $company->setIsActive(true);
        }

        foreach ($definition['endpoints'] as $endpointDef) {
            $endpoint = $company->getEndpoint($endpointDef['type']);
            if (null === $endpoint) {
                $endpoint = new DeliveryEndpoint(
                    company: $company,
                    type: $endpointDef['type'],
                    name: $endpointDef['name'],
                    url: $endpointDef['url'],
                    httpMethod: $endpointDef['method'],
                    responseType: DeliveryResponseType::Json,
                );
                $company->addEndpoint($endpoint);
                $this->em->persist($endpoint);
            } else {
                $endpoint->setName($endpointDef['name']);
                $endpoint->setUrl($endpointDef['url']);
                $endpoint->setHttpMethod($endpointDef['method']);
                $endpoint->setActive(true);
            }
        }

        return $company;
    }

    /**
     * @return list<array{
     *   name: string,
     *   slug: string,
     *   baseUrl: string,
     *   provider: string,
     *   authType: DeliveryAuthType,
     *   authConfig: array<string, mixed>,
     *   mappingConfig: array<string, mixed>,
     *   parametersConfig: array<string, mixed>,
     *   description: string,
     *   endpoints: list<array{type: DeliveryEndpointType, name: string, url: string, method: DeliveryHttpMethod}>
     * }>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => 'First Delivery',
                'slug' => 'first-delivery',
                'baseUrl' => 'https://www.firstdeliverygroup.com/api/v2',
                'provider' => 'first_delivery',
                'authType' => DeliveryAuthType::Bearer,
                'authConfig' => [
                    'credentialFields' => [
                        [
                            'key' => 'token',
                            'label' => 'Jeton API',
                            'type' => 'password',
                            'required' => true,
                            'hint' => 'Bearer token fourni par First Delivery Group',
                        ],
                    ],
                ],
                'mappingConfig' => [
                    'Client' => [
                        'nom' => '{{customer.full_name}}',
                        'gouvernerat' => '{{address.governorate}}',
                        'ville' => '{{address.city}}',
                        'adresse' => '{{address.street}}',
                        'telephone' => '{{customer.phone}}',
                    ],
                    'Produit' => [
                        'prix' => '{{order.total}}',
                        'designation' => '{{order.number}}',
                        'nombreArticle' => '{{products.count}}',
                    ],
                ],
                'parametersConfig' => ['timeout' => 20, 'country' => 'TN'],
                'description' => 'First Delivery Group — livraison e-commerce en Tunisie (API Bearer).',
                'endpoints' => [
                    ['type' => DeliveryEndpointType::CreateShipment, 'name' => 'Création colis', 'url' => '/create', 'method' => DeliveryHttpMethod::Post],
                    ['type' => DeliveryEndpointType::TrackShipment, 'name' => 'Suivi', 'url' => '/etat', 'method' => DeliveryHttpMethod::Post],
                    ['type' => DeliveryEndpointType::CancelShipment, 'name' => 'Annulation', 'url' => '/cancel-orders', 'method' => DeliveryHttpMethod::Post],
                    ['type' => DeliveryEndpointType::GetCities, 'name' => 'Localités', 'url' => '/localities', 'method' => DeliveryHttpMethod::Get],
                    ['type' => DeliveryEndpointType::GetLabel, 'name' => 'Étiquette', 'url' => '/print', 'method' => DeliveryHttpMethod::Get],
                ],
            ],
            [
                'name' => 'Navex',
                'slug' => 'navex',
                'baseUrl' => 'https://app.navex.tn',
                'provider' => 'navex',
                'authType' => DeliveryAuthType::Basic,
                'authConfig' => [
                    'credentialFields' => [
                        [
                            'key' => 'token',
                            'label' => 'Jeton d\'authentification',
                            'type' => 'password',
                            'required' => true,
                            'hint' => 'Token confidentiel Navex (HTTP Basic)',
                        ],
                    ],
                ],
                'mappingConfig' => [
                    'nom' => '{{customer.full_name}}',
                    'gouvernerat' => '{{address.governorate}}',
                    'ville' => '{{address.city}}',
                    'adresse' => '{{address.street}}',
                    'tel' => '{{customer.phone}}',
                    'prix' => '{{order.total}}',
                    'designation' => '{{order.number}}',
                    'nb_article' => '{{products.count}}',
                ],
                'parametersConfig' => ['timeout' => 20, 'country' => 'TN'],
                'description' => 'Navex Delivery — société de livraison tunisienne (API widget / Basic auth).',
                'endpoints' => [
                    ['type' => DeliveryEndpointType::CreateShipment, 'name' => 'Création colis', 'url' => '/api/v1/post.php', 'method' => DeliveryHttpMethod::Post],
                    ['type' => DeliveryEndpointType::GetCities, 'name' => 'Gouvernorats', 'url' => '/api/v1/post.php', 'method' => DeliveryHttpMethod::Get],
                ],
            ],
            [
                'name' => 'Intigo',
                'slug' => 'intigo',
                'baseUrl' => 'https://api.intigo.tn',
                'provider' => 'intigo',
                'authType' => DeliveryAuthType::ApiKey,
                'authConfig' => [
                    'headerName' => 'X-Api-Key',
                    'credentialFields' => [
                        [
                            'key' => 'apiKey',
                            'label' => 'Clé API partenaire',
                            'type' => 'password',
                            'required' => true,
                            'hint' => 'Clé fournie dans l\'espace partenaire Intigo',
                        ],
                        [
                            'key' => 'customBaseUrl',
                            'label' => 'URL API (optionnel)',
                            'type' => 'text',
                            'required' => false,
                            'hint' => 'Remplace l\'URL de base si Intigo vous en fournit une dédiée',
                        ],
                    ],
                ],
                'mappingConfig' => [
                    'customerName' => '{{customer.full_name}}',
                    'phone' => '{{customer.phone}}',
                    'address' => '{{address.full_address}}',
                    'city' => '{{address.city}}',
                    'governorate' => '{{address.governorate}}',
                    'amount' => '{{order.total}}',
                    'reference' => '{{order.number}}',
                    'products' => '{{products.list}}',
                ],
                'parametersConfig' => ['timeout' => 20, 'country' => 'TN'],
                'description' => 'Intigo — livraison e-commerce Tunisie. Endpoints ajustables par le super-admin selon la doc partenaire.',
                'endpoints' => [
                    ['type' => DeliveryEndpointType::CreateShipment, 'name' => 'Création colis', 'url' => '/v1/shipments', 'method' => DeliveryHttpMethod::Post],
                    ['type' => DeliveryEndpointType::TrackShipment, 'name' => 'Suivi', 'url' => '/v1/shipments/{tracking}', 'method' => DeliveryHttpMethod::Get],
                    ['type' => DeliveryEndpointType::CancelShipment, 'name' => 'Annulation', 'url' => '/v1/shipments/{tracking}/cancel', 'method' => DeliveryHttpMethod::Post],
                    ['type' => DeliveryEndpointType::GetLabel, 'name' => 'Étiquette', 'url' => '/v1/shipments/{tracking}/label', 'method' => DeliveryHttpMethod::Get],
                    ['type' => DeliveryEndpointType::GetCities, 'name' => 'Villes', 'url' => '/v1/cities', 'method' => DeliveryHttpMethod::Get],
                ],
            ],
        ];
    }
}
