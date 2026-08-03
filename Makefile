.PHONY: help start stop reload test lint check fix install docker-up docker-down logs-service logs-admin

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-16s\033[0m %s\n", $$1, $$2}'

start: ## Start all services in dev mode
	cd service && php start.php start -d
	cd admin && php start.php start -d

stop: ## Stop all services
	cd service && php start.php stop
	cd admin && php start.php stop

reload: ## Graceful reload
	cd service && php start.php reload
	cd admin && php start.php reload

test: ## Run PHPUnit tests
	cd service && php vendor/bin/phpunit

lint: ## Lint PHP syntax
	find service/app service/config admin/app admin/config -name "*.php" -exec php -l {} \; | grep -v "No syntax"

check: ## Run static analysis
	cd service && vendor/bin/phpstan analyse

fix: ## Fix code style
	cd service && vendor/bin/php-cs-fixer fix
	cd admin && vendor/bin/php-cs-fixer fix

install: ## Install Composer dependencies
	cd service && composer install
	cd admin && composer install

docker-up: ## Start Docker containers
	docker-compose up -d

docker-down: ## Stop Docker containers
	docker-compose down

logs-service: ## Tail service logs
	tail -f service/runtime/logs/webman.log

logs-admin: ## Tail admin logs
	tail -f admin/runtime/logs/webman.log
