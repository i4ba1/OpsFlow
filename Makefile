SHELL := /bin/bash
COMPOSE := docker compose

.PHONY: setup up down restart build logs install key migrate seed fresh \
        backend-test frontend-test lint analyse format \
        shell-backend shell-frontend psql help

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

setup: build init-env install key migrate ## One-command local setup (PRD §58)
	@echo ""
	@echo "OpsFlow is up:"
	@echo "  App (nginx):      http://localhost:8080"
	@echo "  API health:       http://localhost:8080/up"
	@echo "  API ping:         http://localhost:8080/api/v1/ping"
	@echo "  Mailpit UI:       http://localhost:8025"
	@echo "  MinIO console:    http://localhost:9001 (opsflow / opsflow-secret)"
	@echo ""

init-env: ## Copy .env.example files if .env files are missing
	@test -f backend/.env || cp backend/.env.example backend/.env
	@test -f frontend/.env || cp frontend/.env.example frontend/.env

build: ## Build the Docker images
	$(COMPOSE) build

up: ## Start all services
	$(COMPOSE) up -d

down: ## Stop all services
	$(COMPOSE) down

restart: down up ## Restart all services

logs: ## Tail logs for all services
	$(COMPOSE) logs -f

install: ## Install backend (composer) and frontend (npm) dependencies
	$(COMPOSE) up -d postgres redis minio minio-init mailpit
	$(COMPOSE) run --rm backend composer install
	$(COMPOSE) run --rm frontend npm install
	$(COMPOSE) up -d

key: ## Generate the Laravel application key
	$(COMPOSE) exec backend php artisan key:generate --force

migrate: ## Run migrations and seed the database
	$(COMPOSE) exec backend php artisan migrate --seed --force

seed: ## Re-run database seeders
	$(COMPOSE) exec backend php artisan db:seed --force

fresh: ## Recreate everything from scratch (destroys volumes)
	$(COMPOSE) down -v --remove-orphans
	$(COMPOSE) build --no-cache

backend-test: ## Run the backend (Pest) test suite
	$(COMPOSE) exec backend php artisan test

frontend-test: ## Run the frontend (Vitest) test suite
	$(COMPOSE) exec frontend npm run test

lint: ## Run backend (Pint) and frontend (ESLint) linters
	$(COMPOSE) exec backend ./vendor/bin/pint --test
	$(COMPOSE) exec frontend npm run lint

analyse: ## Run static analysis (PHPStan + TypeScript) and format checks
	$(COMPOSE) exec backend ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	$(COMPOSE) exec frontend npm run typecheck
	$(COMPOSE) exec frontend npm run format:check

format: ## Auto-fix code style (Pint + Prettier)
	$(COMPOSE) exec backend ./vendor/bin/pint
	$(COMPOSE) exec frontend npm run format

shell-backend: ## Open a shell in the backend container
	$(COMPOSE) exec backend bash

shell-frontend: ## Open a shell in the frontend container
	$(COMPOSE) exec frontend sh

psql: ## Open a psql session on the PostgreSQL container
	$(COMPOSE) exec postgres psql -U opsflow -d opsflow
