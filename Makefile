# Convenience commands for the dockerised Laravel stack.
#
# Most targets wrap `docker compose` for the local dev stack
# (docker-compose.yml at the project root). Production targets explicitly
# point at docker/docker-compose.prod.yml.

SHELL := /bin/bash

# --- Image coordinates ---------------------------------------------------------
APP_IMAGE     ?= laravel-api-boilerplate
APP_IMAGE_TAG ?= latest
PLATFORMS     ?= linux/amd64,linux/arm64
BUILDX_BUILDER ?= laravel-api-builder

# --- Compose files -------------------------------------------------------------
COMPOSE_DEV  := docker compose -f docker-compose.yml
COMPOSE_PROD := docker compose --env-file docker/.env.prod -f docker/docker-compose.prod.yml

# --- Phony declaration --------------------------------------------------------
.PHONY: help \
        build build-local push \
        up down restart logs ps sh tinker \
        migrate migrate-fresh test pint \
        prod-pull prod-up prod-down prod-logs

# --- Help ---------------------------------------------------------------------
help: ## Show this help.
	@awk 'BEGIN {FS = ":.*##"; printf "Available targets:\n"} \
		/^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# --- Builds -------------------------------------------------------------------
# A multi-arch (amd64+arm64) image cannot be loaded back into the local docker
# image store — buildx only supports `--load` for single-platform builds. So:
#
#   - `make build`       : builds for both platforms into the buildx cache.
#                          Useful in CI to validate the build; nothing is left
#                          on the local docker daemon afterwards.
#   - `make push`        : builds + pushes the manifest list to the registry
#                          named by APP_IMAGE. This is the artifact servers
#                          pull from.
#   - `make build-local` : single-arch build for the host platform, loaded
#                          into local docker so you can `docker run` it.
build: ## Build a multi-arch image (linux/amd64,linux/arm64) via buildx (no output to local docker).
	@docker buildx inspect $(BUILDX_BUILDER) >/dev/null 2>&1 || \
		docker buildx create --name $(BUILDX_BUILDER) --driver docker-container --use
	@docker buildx use $(BUILDX_BUILDER)
	docker buildx build \
		--platform $(PLATFORMS) \
		--target prod \
		--file docker/Dockerfile \
		--tag $(APP_IMAGE):$(APP_IMAGE_TAG) \
		.

build-local: ## Build the prod image for the host architecture only (loaded into local docker).
	docker build \
		--target prod \
		--file docker/Dockerfile \
		--tag $(APP_IMAGE):$(APP_IMAGE_TAG) \
		.

push: ## Build and push the multi-arch image to the registry.
	@docker buildx inspect $(BUILDX_BUILDER) >/dev/null 2>&1 || \
		docker buildx create --name $(BUILDX_BUILDER) --driver docker-container --use
	@docker buildx use $(BUILDX_BUILDER)
	docker buildx build \
		--platform $(PLATFORMS) \
		--target prod \
		--file docker/Dockerfile \
		--tag $(APP_IMAGE):$(APP_IMAGE_TAG) \
		--push \
		.

# --- Local dev ----------------------------------------------------------------
up: ## Start the local stack (detached).
	$(COMPOSE_DEV) up -d

down: ## Stop the local stack.
	$(COMPOSE_DEV) down

restart: ## Restart the app + caddy services.
	$(COMPOSE_DEV) restart app caddy

logs: ## Tail logs for all local services.
	$(COMPOSE_DEV) logs -f

ps: ## Show service status.
	$(COMPOSE_DEV) ps

sh: ## Open a shell in the app container.
	$(COMPOSE_DEV) exec app sh

tinker: ## Launch artisan tinker inside the app container.
	$(COMPOSE_DEV) exec app php artisan tinker

migrate: ## Run database migrations.
	$(COMPOSE_DEV) exec app php artisan migrate

migrate-fresh: ## Drop everything and re-run migrations + seed.
	$(COMPOSE_DEV) exec app php artisan migrate:fresh --seed

test: ## Run the PHPUnit suite inside the container.
	$(COMPOSE_DEV) exec app php artisan test

pint: ## Run Laravel Pint formatter on dirty files.
	$(COMPOSE_DEV) exec app vendor/bin/pint --dirty

# --- Production helpers (run on the server) -----------------------------------
# Typical deployment flow on the server:
#   git pull   # to refresh compose files / Caddyfile / .env.prod
#   make prod-pull && make prod-up
prod-pull: ## Pull the latest app image from the registry.
	$(COMPOSE_PROD) pull

prod-up: ## Start (or upgrade) the production stack.
	$(COMPOSE_PROD) up -d

prod-down: ## Stop the production stack.
	$(COMPOSE_PROD) down

prod-logs: ## Tail production logs.
	$(COMPOSE_PROD) logs -f
