.PHONY: install install-prod build
install: ## Install backend and frontend dependencies
	docker compose run --rm app composer install
	npm install

install-prod: ## Build production image from Dockerfile.prod (vendor + frontend baked in)
	docker compose --env-file .env.prod -f docker-compose.prod.yml build app supervisor

build: ## Build frontend assets
	npm run build
