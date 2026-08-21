# Developer entry points. Run from the repository root.

BACKEND := backend
FRONTEND := frontend

.PHONY: help up down reset test test-unit test-application test-integration test-acceptance qa schema-check cs-fix test-mutation front-install front-dev front-test front-lint front-build _ensure-backend _ensure-node _ensure-frontend

help:
	@echo "make up               - build and run the stack (API on http://localhost:8000)"
	@echo "make down             - stop it"
	@echo "make reset            - stop it and throw the machine away"
	@echo "make test             - run all backend test suites"
	@echo "make test-unit        - unit suite only (fast domain feedback)"
	@echo "make test-application - application suite only"
	@echo "make test-integration - integration suite only"
	@echo "make test-acceptance  - acceptance suite only"
	@echo "make qa               - both halves; every CI gate that needs no network"
	@echo "make schema-check     - migration and mapping describe the same table"
	@echo "make cs-fix           - apply code style fixes"
	@echo "make test-mutation    - Infection on Domain + Application (needs pcov/xdebug)"
	@echo ""
	@echo "make front-install    - install the panel's dependencies"
	@echo "make front-dev        - run the panel against the API on :8000"
	@echo "make front-test       - panel test suite"
	@echo "make front-lint       - ESLint, accessibility rules included"
	@echo "make front-build      - production bundle"

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

# Every gate CI runs that needs nothing but this checkout. Two are left out and
# each for its own reason: mutation testing takes minutes rather than seconds,
# and the two dependency audits need the network, which would make this target
# fail on a train. Both have somewhere else to live — `make test-mutation`, and
# the weekly schedule in the two CI workflows.
#
# The panel is in here, and the cost was measured rather than argued. The first
# measurement flattered the decision: cold, this target costs the best part of a
# minute, so the npm gates looked like rounding. Cold is not the case anyone
# lives in. Warm, with the analyser's cache in place, adding the panel roughly
# doubled the target — seconds to more seconds, still well inside the time you
# will wait without thinking about it, but a doubling and not a rounding.
#
# It goes in on the doubling, not on the flattering figure, because what is
# bought is the only property that makes this worth waiting for at all: green
# here means green in CI. A `qa` that passes while the pipeline fails has lied,
# and a gate that has lied once is a gate nobody waits for again.
qa: _ensure-backend _ensure-frontend
	cd $(BACKEND) && vendor/bin/php-cs-fixer fix --dry-run --diff
	cd $(BACKEND) && vendor/bin/phpstan analyse
	cd $(BACKEND) && vendor/bin/deptrac analyse --config-file=deptrac.php
	$(MAKE) schema-check
	cd $(BACKEND) && vendor/bin/phpunit
	cd $(FRONTEND) && npm run lint
	cd $(FRONTEND) && npm test
	cd $(FRONTEND) && npm run build

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

# The panel's targets. `make up` is not among them: the panel is a service of
# the compose stack, so that target already brings it up.
front-install: _ensure-node
	cd $(FRONTEND) && npm ci

front-dev: _ensure-frontend
	cd $(FRONTEND) && npm run dev

front-test: _ensure-frontend
	cd $(FRONTEND) && npm test

front-lint: _ensure-frontend
	cd $(FRONTEND) && npm run lint

front-build: _ensure-frontend
	cd $(FRONTEND) && npm run build

_ensure-backend:
	@test -d $(BACKEND)/vendor || { echo "Backend dependencies not installed yet - run 'composer install' in backend/."; exit 1; }

# Two guards rather than one, because `front-install` is the target that
# creates node_modules and so cannot be made to require it.
#
# The version check is a script rather than an inline one-liner, and
# frontend/scripts/check-node-version.js says why it had to become one.
#
# Missing Node stops the target; a Node below the floor only warns. The
# difference is not timidity, it is what is true: without Node nothing can run,
# whereas the suite does pass today on a version jsdom says it does not
# support.
# Refusing to run a suite that works would be this file deciding, on its own,
# to turn an npm warning into a gate — a change with real consequences for
# whoever is mid-task, and one that has its own ticket.
_ensure-node:
	@command -v node >/dev/null 2>&1 || { echo "Node is not installed. The panel needs it; frontend/package.json says which version."; exit 1; }
	@cd $(FRONTEND) && node scripts/check-node-version.js

_ensure-frontend: _ensure-node
	@test -d $(FRONTEND)/node_modules || { echo "Panel dependencies not installed yet - run 'make front-install'."; exit 1; }
