# Makefile for WordTemplateBundle — Symfony 8 demo (FrankenPHP).
# REQ-DEMO-005: canonical `up` (sleep 5, composer install, Demo started at: …).

COMPOSE_FILE := docker-compose.yml
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
# Use an absolute docker path: an empty PATH entry ("::") resolves to "." and would pick the local
# `docker/` directory (Permission denied) instead of the CLI binary.
DOCKER      := $(shell command -v docker 2>/dev/null || echo /usr/bin/docker)
COMPOSE_BIN := $(shell "$(DOCKER)" compose version >/dev/null 2>&1 && echo "$(DOCKER) compose" || echo "docker-compose")
COMPOSE     := $(COMPOSE_BIN) -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build install shell logs test verify-http cache-clear link-bundle update-bundle ensure-up restart release-check test-e2e demo-screenshots docx-previews

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
	@echo "  make test-e2e       Playwright e2e (REQ-DEMO-013)"
	@echo "  make demo-screenshots  Refresh docs/images/demo/*.webp (pages)"
	@echo "  make docx-previews  Render example templates and results with LibreOffice → docs/images/demo/docx-*.webp"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@rm -f .env.dev
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction --prefer-dist
	@rm -f .env.dev
	@$(COMPOSE) exec -T php php -d memory_limit=256M bin/console cache:clear || true
	@$(COMPOSE) exec -T php php bin/console assets:install public --symlink || true
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	PORT=$${PORT:-8021}; \
	echo "Demo started at: http://localhost:$$PORT"

down:
	$(COMPOSE) down

restart: ensure-up
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

ensure-up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		$(COMPOSE) up -d; \
		sleep 5; \
	fi

install: ensure-up
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction --prefer-dist

shell: ensure-up
	$(COMPOSE) exec php sh

logs:
	$(COMPOSE) logs -f

test: ensure-up
	$(COMPOSE) exec php php bin/console about

verify-http: ensure-up
	@PORT=$$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep '^PORT=' .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	PORT=$${PORT:-8021}; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT/" 2>/dev/null || echo "000"); \
	if [ "$$code" = "200" ] || [ "$$code" = "302" ]; then echo "OK demo HTTP $$code"; else echo "FAIL HTTP $$code"; exit 1; fi

cache-clear: ensure-up
	$(COMPOSE) exec php php bin/console cache:clear

link-bundle: ensure-up
	$(COMPOSE) exec php sh -c "rm -rf vendor/nowo-tech/word-template-bundle && ln -snf /var/word-template-bundle vendor/nowo-tech/word-template-bundle && php bin/console cache:clear"
	@echo "Bundle linked from mount."

update-bundle: ensure-up
	$(COMPOSE) exec -T php composer dump-autoload
	$(COMPOSE) exec -T php php bin/console cache:clear --env=dev --no-warmup || true
	$(COMPOSE) exec -T php composer update nowo-tech/word-template-bundle --no-interaction --prefer-dist
	$(COMPOSE) exec php php bin/console cache:clear --env=dev

release-check: ensure-up update-bundle test verify-http
	@echo "Demo release-check OK."

# REQ-DEMO-013: Playwright e2e and screenshots in the official Playwright image (version = e2e/package.json).
PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v1.64.1-jammy
DEMO_PORT = DEMO_PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); DEMO_PORT=$${DEMO_PORT:-8021}

test-e2e: ensure-up
	@mkdir -p e2e/node_modules
	@$(DEMO_PORT); \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$DEMO_PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=chromium --workers=2'

demo-screenshots: ensure-up
	@mkdir -p ../../docs/images/demo e2e/node_modules
	@$(DEMO_PORT); \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$DEMO_PORT \
		-e SCREENSHOT_DIR=/screenshots \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=screenshots && node scripts/to-webp.mjs /screenshots'
	@echo "Screenshots written under docs/images/demo/ (WebP)"

# "Template in Word → generated document" images: export every /conditionals/examples case, render both .docx
# with LibreOffice (installed on the fly in LIBREOFFICE_IMAGE unless it already has soffice) and compose them.
LIBREOFFICE_IMAGE ?= debian:bookworm-slim
DOCX_PREVIEW_IDS  ?= named-block expression-true expression-false functions nested else one-of-n table table-empty dates loop-invoice loop-paragraphs loop-nested loop-map loop-empty

docx-previews: ensure-up
	@rm -rf e2e/docx-previews && mkdir -p e2e/docx-previews ../../docs/images/demo e2e/node_modules
	$(COMPOSE) exec -T php php bin/console app:conditionals:export-examples /app/e2e/docx-previews
	docker run --rm -v "$(CURDIR)/e2e/docx-previews:/work" -w /work --entrypoint sh $(LIBREOFFICE_IMAGE) -c '\
		(command -v soffice >/dev/null || (apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends libreoffice-writer-nogui fonts-dejavu fonts-crosextra-carlito >/dev/null)) \
		&& soffice -env:UserInstallation=file:///tmp/lo --headless --convert-to png --outdir /work/png *.docx >/dev/null \
		&& chown -R $(shell id -u):$(shell id -g) /work'
	docker run --rm \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && node scripts/docx-side-by-side.mjs /e2e/docx-previews/png /screenshots $(DOCX_PREVIEW_IDS)'
	@echo "Document previews written under docs/images/demo/docx-*.webp"


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
