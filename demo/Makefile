# Aggregate demo targets (FrankenPHP under demo/symfony8).

DEMOS := symfony8

.PHONY: help up up8 down update-bundle release-verify release-verify8 release-check release-check8

help:
	@echo "WordTemplateBundle demo"
	@echo "  make up              Start demo/symfony8"
	@echo "  make up8             Alias for make up"
	@echo "  make down            Stop demo/symfony8"
	@echo "  make update-bundle   Sync mounted bundle in demo/symfony8"
	@echo "  make release-verify  HTTP smoke check (symfony8)"
	@echo "  make release-check   update-bundle + test + verify-http (symfony8)"

up up8:
	@$(MAKE) -C symfony8 up

down:
	@$(MAKE) -C symfony8 down

update-bundle:
	@$(MAKE) -C symfony8 update-bundle

release-verify release-verify8:
	@$(MAKE) -C symfony8 verify-http

release-check release-check8:
	@$(MAKE) -C symfony8 release-check


# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
