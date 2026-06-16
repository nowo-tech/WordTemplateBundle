# Makefile for WordTemplateBundle — Symfony 8 demo (FrankenPHP).
# REQ-DEMO-005: canonical `up` (sleep 5, composer install, Demo started at: …).

COMPOSE_FILE := docker-compose.yml
COMPOSE := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build install shell logs test verify-http cache-clear link-bundle update-bundle ensure-up restart release-check

help:
	@echo "Symfony 8 demo — WordTemplateBundle"
	@echo ""
	@echo "  make up             Start stack, install deps, print Demo started at: …"
	@echo "  make down           Stop containers"
	@echo "  make restart        Restart containers"
	@echo "  make build          Build images (no cache)"
	@echo "  make install        Composer install in container"
	@echo "  make shell          Shell in PHP container"
	@echo "  make logs           Follow logs"
	@echo "  make test           Smoke check (bin/console about)"
	@echo "  make verify-http    HTTP 200/302 check on PORT"
	@echo "  make cache-clear    Clear Symfony cache"
	@echo "  make link-bundle    Symlink bundle from /var/word-template-bundle"
	@echo "  make update-bundle  Sync bundle + autoload + cache (REQ-DEMO-007)"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	docker-compose up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction --prefer-dist
	@docker-compose exec -T php php -d memory_limit=256M bin/console cache:clear || true
	@docker-compose exec -T php php bin/console assets:install public --symlink || true
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	PORT=$${PORT:-8021}; \
	echo "Demo started at: http://localhost:$$PORT"

down:
	docker-compose down

restart: ensure-up
	docker-compose restart

build:
	docker-compose build --no-cache

ensure-up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		docker-compose up -d; \
		sleep 5; \
	fi

install: ensure-up
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction --prefer-dist

shell: ensure-up
	docker-compose exec php sh

logs:
	docker-compose logs -f

test: ensure-up
	docker-compose exec php php bin/console about

verify-http: ensure-up
	@PORT=$$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep '^PORT=' .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	PORT=$${PORT:-8021}; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT/" 2>/dev/null || echo "000"); \
	if [ "$$code" = "200" ] || [ "$$code" = "302" ]; then echo "OK demo HTTP $$code"; else echo "FAIL HTTP $$code"; exit 1; fi

cache-clear: ensure-up
	docker-compose exec php php bin/console cache:clear

link-bundle: ensure-up
	docker-compose exec php sh -c "rm -rf vendor/nowo-tech/word-template-bundle && ln -snf /var/word-template-bundle vendor/nowo-tech/word-template-bundle && php bin/console cache:clear"
	@echo "Bundle linked from mount."

update-bundle: ensure-up
	docker-compose exec -T php composer dump-autoload
	docker-compose exec -T php php bin/console cache:clear --env=dev --no-warmup || true
	docker-compose exec -T php composer update nowo-tech/word-template-bundle --no-interaction --prefer-dist
	docker-compose exec php php bin/console cache:clear --env=dev

release-check: ensure-up update-bundle test verify-http
	@echo "Demo release-check OK."


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
