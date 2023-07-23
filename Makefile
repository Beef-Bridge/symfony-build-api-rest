# Var
LOCAL_HOST        = http://127.0.0.1
LOCAL_PORT_APACHE = 8003
LOCAL_PORT_PMA    = 8083

# Executables
EXEC_PHP      = php
COMPOSER      = composer
GIT           = git
YARN          = yarn
NPM           = npm

# Executables: local only
DOCKER        = docker
DOCKER_COMP   = docker-compose

# Conteneurs
APP_CONTAINER  = www_sf_api_rest

# Alias
SYMFONY       = $(EXEC_PHP) bin/console

# Executables: vendors
PHPUNIT       = ./vendor/bin/phpunit
PHPSTAN       = ./vendor/bin/phpstan
PHP_CS_FIXER  = ./vendor/bin/php-cs-fixer
PHPMETRICS    = ./vendor/bin/phpmetrics

# Executable dans un conteneur
EXEC_CONTAINER = $(DOCKER) exec -w /var/www/ $(APP_CONTAINER)
EXEC_PHP_CONTAINER = $(EXEC_CONTAINER) $(EXEC_PHP)
EXEC_SYMFONY_CLI_CONTAINER = $(EXEC_CONTAINER) symfony
EXEC_SYMFONY_CONTAINER = $(EXEC_PHP_CONTAINER) bin/console
EXEC_PHP_TEST_CONTAINER = $(EXEC_PHP_CONTAINER) bin/phpunit
EXEC_COMPOSER_CONTAINER = $(EXEC_CONTAINER) $(COMPOSER)
EXEC_YARN_CONTAINER = $(EXEC_CONTAINER) $(YARN)

# Couleurs
GREEN = /bin/echo -e "\x1b[32m\#\# $1\x1b[0m"
RED = /bin/echo -e "\x1b[31m\#\# $1\x1b[0m"

## —— Help screen ——
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— App ——
init: ## Init the project
	$(MAKE) docker-start
	$(MAKE) composer-install
	@$(call GREEN, "The application is available at : LOCAL_HOST:LOCAL_PORT_APACHE")

## —— Docker ——
docker-build: ## Build docker containers
	$(DOCKER_COMP) build
docker-start: ## Start app
	$(DOCKER_COMP) up --detach --remove-orphans --force-recreate
docker-down: ## Stop the docker hub
	$(DOCKER_COMP) down --remove-orphans

## —— Tests ——
database-init-test: ## Init database for test
	$(EXEC_SYMFONY_CONTAINER) d:d:d --force --if-exists --env=test
	$(EXEC_SYMFONY_CONTAINER) d:d:c --env=test
	$(EXEC_SYMFONY_CONTAINER) d:m:m --no-interaction --env=test
	$(EXEC_SYMFONY_CONTAINER) d:f:l --no-interaction --env=test

tests-migrate-configuration: ## Migrate configuration tests
	$(EXEC_PHP_TEST_CONTAINER) --migrate-configuration

tests: ## Run all tests
	$(MAKE) database-init-test
	$(EXEC_PHP_TEST_CONTAINER) --testdox tests/Unit/
	$(EXEC_PHP_TEST_CONTAINER) --testdox tests/Functional/

## —— Symfony ——
sf-cli-check-req: ## Run Symfony CLI check requirements
	$(EXEC_SYMFONY_CLI_CONTAINER) check:requirements
cache-clear: ## Clear cache
	$(EXEC_SYMFONY_CONTAINER) cache:clear
fix-perms: ## Fix permissions of all var files
	@chmod -R 777 var/*
purge: ## Purge cache and logs
	@rm -rf var/cache/* var/logs/*

## —— Composer ——
composer-install: ## Install dependencies
	$(EXEC_COMPOSER_CONTAINER) install --no-progress --prefer-dist --optimize-autoloader
composer-dump-env: ## Dump env
	$(EXEC_COMPOSER_CONTAINER) dump-env dev

## —— Database ——
db-init: db-drop db-create db-migrate db-load-fixtures ## Init database with migration

db-create: ## Create database
	$(EXEC_SYMFONY_CONTAINER) doctrine:database:create --if-not-exists

db-drop: ## Drop database
	$(EXEC_SYMFONY_CONTAINER) doctrine:database:drop --force --if-exists

db-update: ## Update database
	$(EXEC_SYMFONY_CONTAINER) doctrine:schema:update --force --dump-sql --complete
	$(EXEC_SYMFONY_CONTAINER) doctrine:migrations:migrate -n

db-validate-schema: ## Valid doctrine mapping
	$(EXEC_SYMFONY_CONTAINER) doctrine:schema:validate --skip-sync

db-migration: ## Create doctrine migration file
	$(EXEC_SYMFONY_CONTAINER) doctrine:migrations:diff

db-migrate: ## Run doctrine migration(s)
	$(EXEC_SYMFONY_CONTAINER) doctrine:migrations:migrate -n

db-load-fixtures: ## Load fixtures
	$(EXEC_SYMFONY_CONTAINER) doctrine:fixtures:load -n

## -- Yarn --
yarn-install: ## Install all Yarn dependencies
	$(EXEC_YARN_CONTAINER) install

yarn-update: ## pdate all Yarn dependencies
	$(EXEC_YARN_CONTAINER) update

yarn-watch: ## Update all Yarn dependencies
	$(EXEC_YARN_CONTAINER) run watch