# Developer entry points. Targets that depend on tooling not yet installed
# fail with a pointer to the ticket that delivers them.

BACKEND := backend

.PHONY: help up test test-unit qa test-mutation _ensure-backend

help:
	@echo "make up             - run the full stack via docker compose (ticket 13)"
	@echo "make test           - run all backend test suites (ticket 03)"
	@echo "make test-unit      - run the fast domain suite only"
	@echo "make qa             - tests + PHPStan + Deptrac + code style"
	@echo "make test-mutation  - Infection mutation testing on Domain + Application"

up:
	@test -f docker-compose.yml || { echo "docker-compose.yml not defined yet (ticket 13)."; exit 1; }
	docker compose up --build

test: _ensure-backend
	cd $(BACKEND) && vendor/bin/phpunit

test-unit: _ensure-backend
	cd $(BACKEND) && vendor/bin/phpunit --testsuite unit

qa: _ensure-backend
	cd $(BACKEND) && vendor/bin/phpunit \
		&& vendor/bin/phpstan analyse \
		&& vendor/bin/deptrac analyse \
		&& vendor/bin/php-cs-fixer fix --dry-run --diff

test-mutation: _ensure-backend
	cd $(BACKEND) && vendor/bin/infection

_ensure-backend:
	@test -d $(BACKEND)/vendor || { echo "Backend dependencies not installed yet — run 'composer install' in backend/ (scaffolding arrives with tickets 02-03)."; exit 1; }
