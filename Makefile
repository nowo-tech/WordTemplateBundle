# WordTemplateBundle — Docker-driven development (REQ-MAKE-001)
.PHONY: help up down build shell ensure-up install test test-coverage coverage-check cs-check cs-fix qa clean composer-sync release-check phpstan rector rector-dry update validate setup-hooks

COMPOSE_FILE ?= docker-compose.yml
COMPOSE      ?= docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP  ?= php

help:
	@echo "WordTemplateBundle — development commands"
	@echo ""
	@echo "  Container: up, down, build, shell"
	@echo "  Dependencies: install"
	@echo "  Tests: test, test-coverage, coverage-check"
	@echo "  Quality: cs-check, cs-fix, rector, rector-dry, phpstan, qa"
	@echo "  Release: release-check, composer-sync"
	@echo "  Git hooks: setup-hooks"
	@echo "  Cleanup: clean"

build:
	$(COMPOSE) build --no-cache

up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@sleep 3
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	@echo "Container ready."

down:
	$(COMPOSE) down

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		$(COMPOSE) up -d; sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction; \
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
	$(COMPOSE) exec -T $(SERVICE_PHP) composer test-coverage

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

release-check:
	@$(MAKE) ensure-up
	@$(MAKE) composer-sync
	@$(MAKE) cs-fix
	@$(MAKE) cs-check
	@$(MAKE) rector-dry
	@$(MAKE) phpstan
	@$(MAKE) coverage-check

clean:
	rm -rf vendor .phpunit.cache coverage .php-cs-fixer.cache coverage-php.txt coverage-output.txt

update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update

validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

setup-hooks:
	@mkdir -p .git/hooks
	@cp -f .githooks/pre-commit .git/hooks/pre-commit
	@chmod +x .git/hooks/pre-commit
