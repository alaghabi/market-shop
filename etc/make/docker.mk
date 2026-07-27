.PHONY: start start-up stop restart
start: ## Start local stack + run migrations
	docker compose up -d
	@table_count=$$(docker compose exec -T database psql -U app -d app -tAc "SELECT COUNT(*) FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'doctrine_migration_versions';"); \
	existing_schema=$$(docker compose exec -T database psql -U app -d app -tAc "SELECT to_regclass('public.boutique') IS NOT NULL;"); \
	migration_table=$$(docker compose exec -T database psql -U app -d app -tAc "SELECT to_regclass('public.doctrine_migration_versions') IS NOT NULL;"); \
	migration_count=0; \
	if [ "$$migration_table" = "t" ]; then migration_count=$$(docker compose exec -T database psql -U app -d app -tAc "SELECT COUNT(*) FROM doctrine_migration_versions;"); fi; \
	if [ "$$table_count" = "0" ]; then \
		echo "Empty database detected; creating the current Doctrine schema."; \
		docker compose run --rm app bin/console doctrine:schema:create --no-interaction; \
		docker compose run --rm app bin/console doctrine:migrations:sync-metadata-storage --no-interaction; \
		docker compose run --rm app bin/console doctrine:migrations:version --add --all --no-interaction; \
	elif [ "$$existing_schema" = "t" ] && [ "$$migration_count" = "0" ] && docker compose run --rm app bin/console doctrine:schema:validate --no-interaction >/dev/null; then \
		echo "Existing Doctrine schema detected; synchronizing migration metadata."; \
		docker compose run --rm app bin/console doctrine:migrations:sync-metadata-storage --no-interaction; \
		docker compose run --rm app bin/console doctrine:migrations:version --add --all --no-interaction; \
	fi
	docker compose run --rm app bin/console doctrine:migrations:migrate --no-interaction
	docker compose run --rm app bin/console app:seed:permissions 2>/dev/null || true
	docker compose run --rm app bin/console app:seed:role-permissions 2>/dev/null || true

start-up: ## Start local stack + seed application data without migrations
	docker compose up -d
	docker compose run --rm app php bin/console app:seed:reference-data
	docker compose run --rm app php bin/console app:seed:subscription-modules
	docker compose run --rm app php bin/console app:seed:subscription-plans
	docker compose run --rm app php bin/console app:seed:quota-definitions
	docker compose run --rm app php bin/console app:seed:permissions
	docker compose run --rm app php bin/console app:seed:role-permissions
	docker compose run --rm app php bin/console app:seed:themes
	docker compose run --rm app php bin/console app:seed:tunisian-delivery-companies
	docker compose run --rm app php bin/console app:seed:extensions
	docker compose run --rm app php bin/console app:seed:suggestion-categories
	docker compose run --rm app php bin/console app:create-super-admin

stop: ## Stop local stack
	docker compose down

restart: stop start ## Restart local stack
