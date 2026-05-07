# Aggregate demo targets (FrankenPHP under demo/symfony7 and demo/symfony8).

.PHONY: help up up8 release-verify release-verify8 release-check release-check8

help:
	@echo "WordTemplateBundle demos"
	@echo "  make up               Start demo/symfony7 (REQ-DEMO-005 message)"
	@echo "  make up8              Start demo/symfony8 (default port 8021)"
	@echo "  make release-verify   HTTP smoke check (symfony7)"
	@echo "  make release-verify8  HTTP smoke check (symfony8)"
	@echo "  make release-check    update-bundle + test + verify-http (symfony7)"
	@echo "  make release-check8   same for symfony8"

up:
	@$(MAKE) -C symfony7 up

up8:
	@$(MAKE) -C symfony8 up

release-verify:
	@$(MAKE) -C symfony7 verify-http

release-verify8:
	@$(MAKE) -C symfony8 verify-http

release-check:
	@$(MAKE) -C symfony7 release-check

release-check8:
	@$(MAKE) -C symfony8 release-check
