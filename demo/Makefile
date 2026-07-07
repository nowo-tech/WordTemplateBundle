# Aggregate demo targets (FrankenPHP under demo/symfony7 and demo/symfony8).

DEMOS := symfony7 symfony8

.PHONY: help up up8 down update-bundle release-verify release-verify8 release-check release-check8
.PHONY: $(foreach d,$(DEMOS),up-$d down-$d update-bundle-$d)

help:
	@echo "WordTemplateBundle demos"
	@echo "  make up DEMO=symfony7|symfony8   Start a demo (or make up / make up8 shortcuts)"
	@echo "  make up8                         Start demo/symfony8 (default port 8021)"
	@echo "  make down DEMO=symfony7|symfony8"
	@echo "  make update-bundle DEMO=symfony7|symfony8"
	@echo "  make release-verify              HTTP smoke check (symfony7)"
	@echo "  make release-verify8             HTTP smoke check (symfony8)"
	@echo "  make release-check               update-bundle + test + verify-http (symfony7)"
	@echo "  make release-check8              same for symfony8"

up-symfony7:
	@$(MAKE) -C symfony7 up

up-symfony8:
	@$(MAKE) -C symfony8 up

down-symfony7:
	@$(MAKE) -C symfony7 down

down-symfony8:
	@$(MAKE) -C symfony8 down

update-bundle-symfony7:
	@$(MAKE) -C symfony7 update-bundle

update-bundle-symfony8:
	@$(MAKE) -C symfony8 update-bundle

up:
	@if [ -n "$(DEMO)" ]; then \
		$(MAKE) up-$(DEMO); \
	else \
		$(MAKE) -C symfony7 up; \
	fi

up8:
	@$(MAKE) -C symfony8 up

down:
	@[ -n "$(DEMO)" ] || { echo "Usage: make down DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) down-$(DEMO)

update-bundle:
	@[ -n "$(DEMO)" ] || { echo "Usage: make update-bundle DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) update-bundle-$(DEMO)

release-verify:
	@$(MAKE) -C symfony7 verify-http

release-verify8:
	@$(MAKE) -C symfony8 verify-http

release-check:
	@$(MAKE) -C symfony7 release-check

release-check8:
	@$(MAKE) -C symfony8 release-check


# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
