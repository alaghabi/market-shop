# Déploiement production OVH

Cette procédure cible une VM Ubuntu OVH de 8 Go RAM pour `hanooti.shop`.
Elle utilise `docker-compose.prod.yml`, FrankenPHP comme reverse proxy, PostgreSQL,
Redis et Keycloak sur le réseau privé Docker. Ollama reste optionnel (`llama3.2:1b`).

## 1. Préparer Ubuntu

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git make ca-certificates curl openssl ufw
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"
```

Reconnecter la session après l'ajout au groupe Docker, puis vérifier :

```bash
docker --version
docker compose version
make --version
```

Activer le firewall :

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from TRUSTED_ADMIN_IP to any port 22 proto tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Remplacer `TRUSTED_ADMIN_IP` par l'adresse IP d'administration réelle.

## 2. DNS

Créer chez OVH :

```text
A     hanooti.shop       IP_PUBLIQUE_VM
A     www.hanooti.shop   IP_PUBLIQUE_VM
A     *.hanooti.shop     IP_PUBLIQUE_VM
```

Keycloak utilise également `auth.hanooti.shop`, couvert par le certificat
wildcard `*.hanooti.shop`.

Chaque boutique est ensuite accessible sans créer une nouvelle entrée DNS :

```text
demo-shop.hanooti.shop  ->  *.hanooti.shop  ->  Caddy  ->  Symfony
autre-shop.hanooti.shop ->  *.hanooti.shop  ->  Caddy  ->  Symfony
```

Symfony lit le premier segment du host, vérifie qu'il appartient à
`ROOT_DOMAIN`, puis recherche le slug correspondant en base. Le résultat est
placé dans `_boutique` et réutilisé par les providers API. Les slugs réservés
comme `www`, `api`, `auth` et `admin` ne peuvent pas être utilisés comme
boutique.

La création d'une boutique ne nécessite donc ni création DNS ni modification
de Caddy. Il suffit de créer un slug valide et de publier la boutique. Le
certificat doit couvrir `hanooti.shop` et `*.hanooti.shop`.

Le certificat TLS doit couvrir `hanooti.shop` et `*.hanooti.shop`. Placer les
fichiers obtenus ici, sans les committer :

```text
secrets/certs/fullchain.pem
secrets/certs/privkey.pem
```

## 3. Variables secrètes

```bash
touch .env.prod
chmod 600 .env.prod
openssl rand -hex 32
```

Remplacer dans `.env.prod` :

- `APP_SECRET`
- `POSTGRES_PASSWORD`
- `REDIS_PASSWORD`
- `MERCURE_JWT_SECRET`
- `SUPER_ADMIN_PASSWORD`
- `KEYCLOAK_ADMIN_PASSWORD`
- `KEYCLOAK_ADMIN_URL=http://keycloak:8080`
- `KEYCLOAK_REALM=hanooti`
- `KEYCLOAK_CLIENT_ID=hanooti-web`
- `KEYCLOAK_PUBLIC_SCHEME=https`
- `KEYCLOAK_PUBLIC_PORT=`
- `KEYCLOAK_REDIRECT_SYNC_ENABLED=true`
- `KEYCLOAK_ISSUER=https://auth.hanooti.shop/realms/hanooti`
- `KEYCLOAK_DISCOVERY_BASE_URI=https://auth.hanooti.shop/realms/hanooti/`
- `KEYCLOAK_AUDIENCE=hanooti-api`
- `MAILER_DSN` avec le SMTP Brevo gratuit ou le fournisseur choisi

Les mots de passe PostgreSQL et Redis doivent être composés de caractères URL
sûrs si les DSN Redis ou PostgreSQL sont construits manuellement. Une valeur
produite par `openssl rand -hex 32` convient.

## 4. Construire et démarrer

L'image production est construite avec `etc/docker/frankenphp/Dockerfile.prod`
(multi-stage: Composer, Node build, FrankenPHP). Le service `supervisor`
démarre automatiquement cron + workers via `CONTAINER_ROLE=supervisor`.

```bash
make prod-build
make prod-up
# équivalent:
# docker compose --env-file .env.prod -f docker-compose.prod.yml build app supervisor
# docker compose --env-file .env.prod -f docker-compose.prod.yml up -d
```

Sur une base PostgreSQL déjà existante, créer le schéma Keycloak une seule fois
avant son premier démarrage :

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T database \
  sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
  -c "CREATE SCHEMA IF NOT EXISTS keycloak AUTHORIZATION CURRENT_USER;"'
```

Puis redémarrer Keycloak :

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml restart keycloak
```

Vérifier l'état :

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml ps
docker compose --env-file .env.prod -f docker-compose.prod.yml logs --tail=100 app
```

## 5. Base de données et initialisation

Ne pas exécuter `doctrine:schema:create` en production.

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app \
  php bin/console doctrine:migrations:migrate --no-interaction --env=prod

docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app \
  php bin/console app:seed:reference-data --env=prod
docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app \
  php bin/console app:seed:subscription-modules --env=prod
docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app \
  php bin/console app:seed:subscription-plans --env=prod
docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app \
  php bin/console app:seed:permissions --env=prod
docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app \
  php bin/console app:create-super-admin --env=prod
```

Le realm `hanooti`, les clients `hanooti-api` et `hanooti-web`, ainsi que les
rôles Keycloak doivent ensuite être créés dans Keycloak production. Le fichier
`etc/keycloak/realm-dev.json` est réservé au développement et ne contient aucun
secret de production.

Le client public `hanooti-web` doit autoriser l'URL de la plateforme :

```text
https://hanooti.shop/*
```

Les redirect URIs de chaque boutique publiée sont ensuite ajoutées automatiquement
par Symfony avec la commande suivante :

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app \
  php bin/console app:keycloak:sync-redirect-uris --env=prod
```

La commande est idempotente et peut être relancée après une création ou un
changement de slug. Les nouvelles boutiques publiées sont aussi synchronisées
automatiquement après leur publication. Les domaines personnalisés publiés sont
ajoutés avec leur propre origine HTTPS.

Les Identity Providers Google et Microsoft doivent être configurés
dans le realm de production avec leurs callbacks Keycloak respectifs. Les
secrets restent dans Keycloak ou dans un gestionnaire de secrets, jamais dans
le frontend.

Le compte initial est `super-hanooti@gmail.com`. Le mot de passe est lu depuis
`SUPER_ADMIN_PASSWORD` et n'est jamais affiché en production.

## 6. Déploiement GitHub Actions

Le dépôt contient deux workflows :

- `.github/workflows/ci.yml` valide le backend, le frontend et l'image Docker de production sur chaque Pull Request et chaque push sur `main`.
- `.github/workflows/deploy.yml` déploie automatiquement la dernière révision validée de `main` vers OVH.

Créer un environnement GitHub nommé `production`, puis ajouter ces secrets :

```text
OVH_HOST
OVH_USER
OVH_APP_DIR
OVH_SSH_PRIVATE_KEY
OVH_KNOWN_HOSTS
```

`OVH_APP_DIR` doit pointer vers le répertoire de l'application sur la VM. Le
fichier `.env.prod` et `secrets/certs/` restent sur la VM et ne sont jamais
envoyés par le workflow. Le déploiement crée une sauvegarde PostgreSQL avant
les migrations et vérifie ensuite `https://hanooti.shop/api/health`.

Le workflow peut aussi être lancé manuellement depuis l'onglet **Actions** avec
`Deploy production`.

## 7. Sauvegarde minimale

Avant chaque migration, produire un dump PostgreSQL et le copier hors de la VM :

```bash
mkdir -p backups
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T database \
  sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > "backups/hanooti-$(date +%F-%H%M).sql.gz"
```

Mettre ensuite `backups/` sur OVH Object Storage ou un autre stockage externe.
Tester régulièrement une restauration sur une base séparée.

## 8. Ressources et Ollama

Les limites sont volontairement fixées pour une VM de 8 Go : PostgreSQL 2 Go,
application 1,5 Go, workers 1,5 Go, Keycloak 768 Mo, Ollama 1,75 Go. Cette
configuration est serrée. Désactiver Ollama sur une VM de 8 Go, ou utiliser au
moins 12 Go si Keycloak et Ollama doivent fonctionner en permanence. Le chatbot utilise au maximum
1024 tokens de contexte, 256 tokens de sortie, 8 messages d'historique et 2 threads CPU.
Si la VM manque de mémoire,
désactiver temporairement Ollama avant d'augmenter la taille de la VM.

Pour basculer de modèle, modifier dans `.env.prod` `OLLAMA_MODEL` et
`OLLAMA_ALLOWED_MODELS`, puis reconstruire le service Ollama. Le modèle choisi doit
rester compatible avec la limite mémoire de la VM. Après validation du nouveau
modèle, supprimer l'ancien du volume Ollama pour récupérer l'espace disque :

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml exec ollama \
  ollama rm ANCIEN_MODELE
```
