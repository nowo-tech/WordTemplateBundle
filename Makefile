# WordTemplateBundle — Docker-driven development (REQ-MAKE-001)
SHELL := /bin/bash
.PHONY: help up down down-dev build shell ensure-up install test test-coverage coverage-check cs-check cs-fix qa clean composer-sync release-check release-check-demos demo-smoke phpstan rector rector-dry update validate setup-hooks check-no-cursor-coauthor check-open-prs strip-cursor-coauthor-from-history

COMPOSE_FILE ?= docker-compose.yml
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE_BIN ?= $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
COMPOSE     ?= $(COMPOSE_BIN) -f $(COMPOSE_FILE)
SERVICE_PHP  ?= php
COMPOSER_INSTALL = $(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'composer install --no-interaction || { rm -rf vendor; composer clear-cache; composer install --no-interaction; }'

help:
	@echo "WordTemplateBundle — development commands"
	@echo ""
	@echo "  Container: up, down, down-dev, build, shell"
	@echo "  Dependencies: install"
	@echo "  Tests: test, test-coverage, coverage-check"
	@echo "  Quality: cs-check, cs-fix, rector, rector-dry, phpstan, qa"
	@echo "  Release: check-open-prs, demo-smoke, release-check, composer-sync"
	@echo "  Git hooks: setup-hooks"
	@echo "  Cleanup: clean"

build:
	$(COMPOSE) build --no-cache

up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@sleep 3
	$(COMPOSER_INSTALL)
	@echo "Container ready."

down:
	$(COMPOSE) down

down-dev: down
	@echo "Dev container stopped."

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		$(COMPOSE) up -d; sleep 3; \
		$(COMPOSER_INSTALL); \
	fi

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install

test: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	$(COMPOSE) exec -T $(SERVICE_PHP) composer test

test-coverage: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	$(COMPOSE) exec -T $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	./.scripts/php-coverage-percent.sh coverage-php.txt

coverage-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	$(COMPOSE) exec -T $(SERVICE_PHP) composer coverage-check

cs-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

phpstan: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

rector: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

rector-dry: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

qa: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer qa

composer-sync: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-install

release-check: check-no-cursor-coauthor check-open-prs ensure-up composer-sync cs-check rector-dry phpstan coverage-check release-check-demos

release-check-demos:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo release-check; else echo "No demo/Makefile — skip"; fi

demo-smoke:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo release-check; else echo "No demo/Makefile — skip demo-smoke"; fi

clean:
	rm -rf vendor .phpunit.cache coverage .php-cs-fixer.cache coverage-php.txt coverage-output.txt

update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update

validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

check-open-prs:
	@chmod +x .scripts/check-open-prs.sh
	@GH_REPO=nowo-tech/WordTemplateBundle ./.scripts/check-open-prs.sh

setup-hooks:
	@mkdir -p .git/hooks
	@if [ -f .githooks/pre-commit ]; then \
		cp -f .githooks/pre-commit .git/hooks/pre-commit; \
		chmod +x .git/hooks/pre-commit; \
		echo "✅ pre-commit hook installed."; \
	fi
	@if [ -f .githooks/commit-msg ]; then \
		cp -f .githooks/commit-msg .git/hooks/commit-msg; \
		chmod +x .git/hooks/commit-msg; \
		echo "✅ commit-msg hook installed (REQ-GIT-001)."; \
	fi


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main
