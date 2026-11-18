.DEFAULT_GOAL := help

RUN = docker compose run --rm --user $$(id -u):$$(id -g) php

help: ## List targets
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F ':.*## ' '{printf "%-10s %s\n", $$1, $$2}'

up: ## Start the stack (PHP + MySQL) in the background
	docker compose up -d --wait

down: ## Stop the stack and drop its volumes
	docker compose down -v --remove-orphans

install: up ## Install Composer dependencies inside the PHP container
	$(RUN) composer install --no-interaction --no-progress

test: up ## Run the PHPUnit suite (pass extra options with ARGS="...")
	$(RUN) vendor/bin/phpunit $(ARGS)

lint: up ## Run PHPCS
	$(RUN) vendor/bin/phpcs

shell: up ## Open a shell inside the PHP container
	$(RUN) bash

.PHONY: help up down install test lint shell
