# Hanooti — Guide d'utilisation

## Super Admin & Administrateurs Boutique

---

## 1. Présentation

Hanooti est une plateforme SaaS multi-boutique permettant de gérer des boutiques en ligne, leurs produits, commandes, stocks, livraisons, abonnements et programmes de fidélité.

### Rôles et hiérarchie

```
ROLE_SUPER_ADMIN → ROLE_BOUTIQUE_ADMIN → ROLE_CAISSIER → ROLE_USER
                                         → ROLE_CUSTOMER
```

- **Super Admin** : pilotage global de la plateforme, accès à toutes les boutiques
- **Boutique Admin** : gestion d'une ou plusieurs boutiques (produits, commandes, stocks)
- **Caissier** : caisse POS, gestion des ventes comptoir
- **Client (Customer)** : navigation, achat sur le front-office

---

## 2. Dashboard

### Tableau de bord Super Admin (`/admin/super-admin-dashboard`)
- Vue d'ensemble de la plateforme
- Nombre de boutiques actives
- Revenus totaux et répartition
- Commandes récentes toutes boutiques confondues

### Tableau de bord Boutique (`/admin/boutique-dashboard`)
- KPI de la boutique sélectionnée
- Commandes récentes
- Performance commerciale

### Dashboard Back-office (`/admin`)
- Vue générale SaaS multi-boutique
- Accès rapide aux modules principaux

---

## 3. Gestion des Boutiques

### Création et administration (`/admin/boutiques`)
- Créer une nouvelle boutique (nom, slug)
- Activer/désactiver une boutique
- Statuts disponibles : Brouillon, Publiée, Suspendue
- Associer des administrateurs

### Paramètres boutique (`/admin/settings`)
- **Identité** : nom, domaine, email, téléphone, adresse
- **Branding** : logo, couleurs principales
- **Livraison** : configuration des comptes transporteurs
- **Réseaux sociaux** : Instagram, Facebook
- **Meta Pixel** : identifiant Facebook Pixel pour le suivi des conversions (par boutique)
- **Notifications** : email, alerte stock bas, rapport hebdo

### Personnalisation Front-office (`/admin/theme`)
- Couleurs et thème visuel
- Navigation et pages mises en avant
- Catégories en vedette
- Icônes personnalisées

---

## 4. Gestion des Utilisateurs

### Utilisateurs boutique (`/admin/users`)
- Liste des administrateurs et caissiers
- Création et modification des comptes
- Attribution des rôles (admin, caissier)

### Clients (`/admin/customers`)
- Comptes clients enregistrés
- Historique d'achats
- Segmentation client

---

## 5. Catalogue Produits

### Produits (`/admin/products`)
- Création et gestion du catalogue
- Prix, images, descriptions
- Visibilité (publié/brouillon)
- Variantes et options

### Catégories (`/admin/categories`)
- Organisation du catalogue par catégories
- Hiérarchie et arborescence

### Filtres produits
- Filtres personnalisés par boutique
- Valeurs de filtre (tailles, couleurs, etc.)

---

## 6. Stock & Inventaire

### Inventaire (`/admin/product-inventory`)
- Tableau de bord des stocks
- Stock critique et alertes de réapprovisionnement
- Vue d'ensemble des niveaux de stock

### Mouvements de stock (`/admin/stock-movements`)
- Entrées (réceptions fournisseur)
- Sorties (ventes, retours)
- Corrections et ajustements
- Traçabilité complète

---

## 7. Commandes & Livraison

### Commandes (`/admin/orders`)
- Liste des commandes web et POS
- Préparation et traitement
- Statuts : Brouillon, Confirmée, Payée, Expédiée, Livrée, Annulée
- Paiement sécurisé

### Détail de commande (`/admin/orders/ord-xxxx`)
- Vue détaillée avec timeline
- Informations client et adresse de livraison
- Articles commandés
- Tracking et statut de livraison

### Caisse POS (`/admin/pos`)
- Vente comptoir
- Panier rapide
- Encaissement local

### Système de livraison

#### Transporteurs (`/admin/delivery-companies`)
- Configuration des sociétés de livraison disponibles
- Endpoints API (authentification, soumission, tracking)
- Activer/désactiver un transporteur
- Réservé au Super Admin

#### Comptes livraison (`/admin/delivery-accounts`)
- Identifiants cryptés par boutique et transporteur
- Chiffrement AES-256-GCM (clé dérivée du secret applicatif)
- Vérification des comptes (test de connexion)
- Statut : vérifié / non vérifié / erreur
- Activation/désactivation

#### Processus automatique
Les commandes sont soumises aux transporteurs automatiquement :
1. **Soumission** : dès qu'une commande est payée, elle est envoyée au transporteur (cron toutes les 60s)
2. **Livraison** : 60s après soumission, la commande est marquée livrée (délai de simulation)
3. **Réessai** : les échecs de soumission sont retentés jusqu'à 5 fois (cron toutes les 60s)
4. **Tracking** : le numéro de suivi est stocké sur la commande

---

## 8. Marketing

### Promotions (`/admin/promotions`)
- Création de promotions globales ou par catégorie
- Types : réduction, offre spéciale
- Priorité métier
- Activation programmée

### Fidélité (`/admin/loyalty`)
- Comptes fidélité clients
- Attribution et gestion des points
- Transactions de points
- Programmes de fidélité

### Sponsors (`/admin/sponsors`)
- Gestion des sponsors de la plateforme
- Association sponsor ↔ boutique
- Visibilité et ordre d'affichage

### Meta Pixel
Le tracking Facebook Pixel est disponible à deux niveaux :

**Pixel boutique** (configuré par le boutique admin dans `/admin/settings`)
- ID Pixel propre à chaque boutique
- Tiré sur toutes les pages publiques de la boutique (storefront, fiche produit, panier, checkout, confirmation)
- Laissez vide pour désactiver

**Pixel applicatif** (configuré par le super admin dans `/admin/super-admin-dashboard`)
- ID Pixel global tiré sur toutes les boutiques de la plateforme
- Permet un suivi consolidé au niveau de l'application
- Stocké dans `var/data/app_config.json`

Les deux pixels sont initialisés automatiquement côté front-end via le hook `useMetaPixel`. La librairie Facebook Pixel est chargée dynamiquement lors de la première visite sur une page publique.

---

## 9. Abonnements

### Plans disponibles

| Plan    | Durée | Prix    |
|---------|-------|---------|
| Gratuit | 1 mois  | 0 €     |
| 3 mois  | 3 mois  | 29,99 € |
| 6 mois  | 6 mois  | 49,99 € |
| 1 an    | 12 mois | 89,99 € |

### Gestion (`/admin/subscriptions`)
- Création d'abonnement pour une boutique
- Acceptation (valide l'abonnement)
- Statuts : En attente, Actif, Expiré, Refusé
- Historique des abonnements
- Dates de début, fin et validation
- Expiration automatique via cron

> ⚠️ Seuls les Super Admins peuvent accepter les abonnements

---

## 10. Chat & Assistance

### Messagerie temps réel (`/admin/chat`)
- Messages clients/professionnels
- Historique des conversations
- Notifications en temps réel (Mercure)

### Chatbot (`/admin/chatbot-config`)
- Configuration de l'assistant automatique
- Scénarios et réponses prédéfinies
- Ton de l'assistant
- Règles de déclenchement

---

## 11. Configuration Système

### Design System (`/admin/design-system`)
- Tokens de design du back-office
- Couleurs, composants et règles visuelles
- Personnalisation de l'interface d'administration

---

## 12. API et Intégrations

### API REST
L'API est accessible via `/api/` avec authentification Bearer Token.

**Endpoints principaux :**
- `GET /api/boutiques` — liste publique des boutiques (inclut `metaPixelId`)
- `GET /api/boutiques/{slug}/products` — catalogue public
- `GET /api/public/meta-pixel` — retourne l'ID Pixel applicatif
- `GET /api/admin/*` — admin protégé (ROLE_BOUTIQUE_ADMIN)
- `GET/POST /api/admin/app-config` — config applicative (ROLE_SUPER_ADMIN)
- `POST /api/auth/login` — authentification
- `POST /api/auth/register` — inscription

### Endpoints Livraison
- `GET /api/delivery/companies` — transporteurs disponibles
- `GET/POST/PATCH/DELETE /api/boutiques/{id}/delivery-accounts` — comptes livraison
- `POST /api/boutiques/{id}/delivery-accounts/{id}/verify` — vérification

---

## 13. Commandes Console

### Création d'un Super Admin
```bash
php bin/console app:create-super-admin
```

### Abonnements
```bash
php bin/console app:subscription-expiry   # Expiration automatique
```

### Livraison
```bash
php bin/console app:delivery-retry          # Réessai des échecs
php bin/console app:delivery-verify-accounts # Vérification des comptes
php bin/console app:delivery-tracking-sync   # Synchronisation des suivis
```

### Maintenance
```bash
php bin/console app:cleanup-old-data        # Nettoyage anciennes données
```

---

## 14. Infrastructure

### Stack technique
- **Backend** : PHP 8.3 / Symfony 7 / API Platform 3
- **Frontend** : React 18 (TypeScript) avec Webpack Encore
- **Base de données** : PostgreSQL 16
- **Cache** : Redis 7
- **Serveur** : FrankenPHP / Caddy / Traefik
- **Temps réel** : Mercure
- **Docker** : docker-compose multi-services

### Services
| Service | Port    | Description          |
|---------|---------|----------------------|
| App     | 8082    | Application web      |
| Traefik | 8083/84 | Reverse proxy        |
| DB      | 5432    | PostgreSQL           |
| Redis   | 6379    | Cache                |
| Mercure | (interne) | Notifications temps réel |

### Traitement asynchrone
1. La confirmation d'une commande dispatch `CreateShipmentMessage` dans `async_logistics`.
2. Le worker Messenger soumet la commande au transporteur sans bloquer l'interface.
3. Mercure publie le résultat et le header recharge les notifications.
4. `app:delivery-retry` retente les soumissions échouées (maximum 5).
5. `app:delivery-tracking-sync` synchronise les expéditions non finalisées.

---

## 15. Dépannage

### Erreurs courantes

| Problème                    | Cause possible                  | Solution                              |
|-----------------------------|---------------------------------|---------------------------------------|
| Compte livraison non vérifié | Identifiants incorrects         | Vérifier login/password et réessayer  |
| Abonnement expiré           | Plan gratuit arrivé à terme     | Souscrire un nouveau plan             |
| Commande non soumise        | Aucun compte transporteur actif | Configurer un compte de livraison     |
| Stock négatif               | Correction manuelle erronée     | Vérifier les mouvements de stock      |
| Impossible de se connecter  | Token expiré ou invalide        | Se reconnecter via `/admin`           |

### Sécurité
- Les mots de passe sont hashés avec l'algorithme par défaut de Symfony (auto)
- Les identifiants transporteurs sont chiffrés en AES-256-GCM
- L'authentification API utilise des Bearer Token
- Accès aux endpoints contrôlé par hiérarchie de rôles
- Données sensibles jamais exposées dans les réponses API
