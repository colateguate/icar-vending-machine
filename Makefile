# Developer entry points. Run from the repository root.

BACKEND := backend

.PHONY: help up test test-unit test-application test-integration test-acceptance qa cs-fix test-mutation _ensure-backend

help:
	@echo "make up               - run the full stack via docker compose (ticket 13)"
	@echo "make test             - run all backend test suites"
	@echo "make test-unit        - unit suite only (fast domain feedback)"
	@echo "make test-application - application suite only"
	@echo "make test-integration - integration suite only"
	@echo "make test-acceptance  - acceptance suite only"
	@echo "make qa               - code style + PHPStan + Deptrac + all tests"
	@echo "make cs-fix           - apply code style fixes"
	@echo "make test-mutation    - Infection on Domain + Application (needs pcov/xdebug)"

up:
	@test -f docker-compose.yml || { echo "docker-compose.yml not defined yet (ticket 13)."; exit 1; }
	docker compose up --build

test: _ensure-backend
	cd $(BACKEND) && vendor/bin/phpunit

test-unit: _ensure-backend
	cd $(BACKEND) && vendor/bin/phpunit --testsuite unit

test-application: _ensure-backend
	cd $(BACKEND) && vendor/bin/phpunit --testsuite application

test-integration: _ensure-backend
	cd $(BACKEND) && vendor/bin/phpunit --testsuite integration

test-acceptance: _ensure-backend
	cd $(BACKEND) && vendor/bin/phpunit --testsuite acceptance

qa: _ensure-backend
	cd $(BACKEND) && vendor/bin/php-cs-fixer fix --dry-run --diff
	cd $(BACKEND) && vendor/bin/phpstan analyse
	cd $(BACKEND) && vendor/bin/deptrac analyse --config-file=deptrac.php
	cd $(BACKEND) && vendor/bin/phpunit

cs-fix: _ensure-backend
	cd $(BACKEND) && vendor/bin/php-cs-fixer fix

# Runs only when the business core has code; mutating an empty tree is an error.
test-mutation: _ensure-backend
	@cd $(BACKEND) && if find src/VendingMachine/Domain src/VendingMachine/Application -name '*.php' 2>/dev/null | grep -q .; then \
		vendor/bin/infection --threads=max --show-mutations; \
	else \
		echo "No Domain/Application code yet - skipping mutation testing (arrives with ticket 04)."; \
	fi

_ensure-backend:
	@test -d $(BACKEND)/vendor || { echo "Backend dependencies not installed yet - run 'composer install' in backend/."; exit 1; }
