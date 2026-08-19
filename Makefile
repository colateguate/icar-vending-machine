# Developer entry points. Run from the repository root.

BACKEND := backend

.PHONY: help up down reset test test-unit test-application test-integration test-acceptance qa schema-check cs-fix test-mutation _ensure-backend

help:
	@echo "make up               - build and run the stack (API on http://localhost:8000)"
	@echo "make down             - stop it"
	@echo "make reset            - stop it and throw the machine away"
	@echo "make test             - run all backend test suites"
	@echo "make test-unit        - unit suite only (fast domain feedback)"
	@echo "make test-application - application suite only"
	@echo "make test-integration - integration suite only"
	@echo "make test-acceptance  - acceptance suite only"
	@echo "make qa               - code style + PHPStan + Deptrac + schema + all tests"
	@echo "make schema-check     - migration and mapping describe the same table"
	@echo "make cs-fix           - apply code style fixes"
	@echo "make test-mutation    - Infection on Domain + Application (needs pcov/xdebug)"

# The whole thing, from a clone with no PHP installed: builds the image,
# migrates, provisions the machine and serves the API on :8000.
up:
	docker compose up --build

down:
	docker compose down

# Also throws the machine away. The next `make up` starts from the
# catalogue of the brief again, which is the way to reset a machine that
# an evaluator has emptied.
reset:
	docker compose down --volumes

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

# The mapping and the migration have to describe the same table. Checked
# against a throwaway database so it also works on a fresh clone, where
# nobody has run a migration yet.
schema-check: _ensure-backend
	cd $(BACKEND) && rm -f var/qa-schema.db
	cd $(BACKEND) && DATABASE_URL="sqlite:///%kernel.project_dir%/var/qa-schema.db" php bin/console doctrine:migrations:migrate --no-interaction --quiet
	cd $(BACKEND) && DATABASE_URL="sqlite:///%kernel.project_dir%/var/qa-schema.db" php bin/console doctrine:schema:validate
	cd $(BACKEND) && rm -f var/qa-schema.db

qa: _ensure-backend
	cd $(BACKEND) && vendor/bin/php-cs-fixer fix --dry-run --diff
	cd $(BACKEND) && vendor/bin/phpstan analyse
	cd $(BACKEND) && vendor/bin/deptrac analyse --config-file=deptrac.php
	$(MAKE) schema-check
	cd $(BACKEND) && vendor/bin/phpunit

cs-fix: _ensure-backend
	cd $(BACKEND) && vendor/bin/php-cs-fixer fix

# Runs only when the business core has code; mutating an empty tree is an error.
# XDEBUG_MODE is set here rather than in php.ini: Xdebug's coverage mode makes
# every other command slower, and this is the only one that needs it.
test-mutation: _ensure-backend
	@cd $(BACKEND) && if find src/VendingMachine/Domain src/VendingMachine/Application -name '*.php' 2>/dev/null | grep -q .; then \
		XDEBUG_MODE=coverage vendor/bin/infection --threads=max --show-mutations; \
	else \
		echo "No Domain/Application code yet - skipping mutation testing (arrives with ticket 04)."; \
	fi

_ensure-backend:
	@test -d $(BACKEND)/vendor || { echo "Backend dependencies not installed yet - run 'composer install' in backend/."; exit 1; }
