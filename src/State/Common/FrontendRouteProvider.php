<?php

namespace App\State\Common;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Common\FrontendRouteResource;

/** @implements ProviderInterface<FrontendRouteResource> */
final class FrontendRouteProvider implements ProviderInterface
{
    /** @return array<FrontendRouteResource>|FrontendRouteResource|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|FrontendRouteResource|null
    {
        unset($context);

        $routes = $this->routes();

        if ($operation instanceof GetCollection) {
            return array_values($routes);
        }

        $slug = (string) ($uriVariables['slug'] ?? '');

        return $routes[$slug] ?? null;
    }

    /** @return array<string, FrontendRouteResource> */
    private function routes(): array
    {
        $definitions = [
            ['home', 'Accueil Hanooti', '/', 'Public', 'Page publique avec toutes les boutiques et les vitrines actives.'],
            ['boutiques', 'Toutes les boutiques', '/boutiques', 'Public', 'Marketplace publique pour découvrir les boutiques locales.'],
            ['boutique-luxe-paris', 'Luxe Paris', '/boutiques/luxe-paris', 'Public', 'Front-office client de la boutique Luxe Paris.'],
            ['robe-de-soiree', 'Robe de Soirée', '/products/robe-de-soiree', 'Public', 'Fiche produit publique avec galerie, prix et disponibilité.'],
            ['dashboard', 'Dashboard Back-office', '/admin', 'Admin', 'Vue générale du SaaS multi-boutique.'],
            ['super-admin-dashboard', 'Tableau de bord Super Admin', '/admin/super-admin-dashboard', 'Admin', 'Pilotage plateforme, revenus, boutiques et santé du SaaS.'],
            ['boutique-dashboard', 'Boutique Management Dashboard', '/admin/boutique-dashboard', 'Boutique', 'KPI opérationnels, commandes récentes et performance boutique.'],
            ['boutiques-admin', 'Gestion des Boutiques', '/admin/boutiques', 'Boutique', 'Créer, activer et administrer les boutiques de la plateforme.'],
            ['settings', 'Paramètres boutique', '/admin/settings', 'Boutique', 'Logo, domaine, réseaux sociaux et options boutique.'],
            ['theme', 'Personnalisation du Front-office', '/admin/theme', 'Boutique', 'Couleurs, navigation, pages et catégories mises en avant.'],
            ['users', 'Utilisateurs boutique', '/admin/users', 'Admin', 'Admins boutique, caissiers et droits opérationnels.'],
            ['customers', 'Clients', '/admin/customers', 'Commerce', 'Comptes clients, historique d’achat et segments.'],
            ['categories', 'Catégories', '/admin/categories', 'Commerce', 'Organisation du catalogue par catégories boutique.'],
            ['products', 'Produits', '/admin/products', 'Commerce', 'Catalogue produits, prix, images et visibilité.'],
            ['product-inventory', 'Product Inventory', '/admin/product-inventory', 'Stock', 'Inventaire, stock critique, mouvements et alertes.'],
            ['stock-movements', 'Mouvements de stock', '/admin/stock-movements', 'Stock', 'Entrées, sorties, corrections et traçabilité stock.'],
            ['orders', 'Orders & Fulfillment', '/admin/orders', 'Order', 'Commandes web, préparation, paiement et livraison.'],
            ['pos', 'Caisse POS', '/admin/pos', 'Order', 'Vente comptoir, panier rapide et encaissement local.'],
            ['promotions', 'Marketing & Promotions', '/admin/promotions', 'Marketing', 'Promotions globales, catégories, produits et priorité métier.'],
            ['loyalty', 'Fidélité', '/admin/loyalty', 'Loyalty', 'Comptes fidélité, points et transactions client.'],
            ['sponsors', 'Sponsors', '/admin/sponsors', 'Sponsor', 'Sponsors plateforme et associations par boutique.'],
            ['chat', 'Messagerie', '/admin/chat', 'Chat', 'Messagerie temps réel avec les clients.'],
            ['chatbot-config', 'Configuration du Chatbot', '/admin/chatbot-config', 'Chatbot', 'Scénarios, réponses, ton et règles de l’assistant boutique.'],
            ['design-system', 'Design System', '/admin/design-system', 'System', 'Tokens, couleurs, composants et règles visuelles du back-office.'],
        ];

        $routes = [];

        foreach ($definitions as $definition) {
            [$slug, $title, $path, $section, $description] = $definition;
            $routes[$slug] = new FrontendRouteResource($slug, $title, $path, $section, $description);
        }

        return $routes;
    }
}
