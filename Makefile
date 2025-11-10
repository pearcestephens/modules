.PHONY: help install test coverage stan cs-fix cs-check mutation ci clean

# Colors for output
GREEN  := \033[0;32m
YELLOW := \033[0;33m
RED    := \033[0;31m
RESET  := \033[0m

##
## 🚀 CIS MODULES - ENTERPRISE QUALITY AUTOMATION
## ===============================================

help: ## Show this help message
	@echo ""
	@echo "$(GREEN)Available Commands:$(RESET)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""

##
## 📦 Setup & Dependencies
## -----------------------

install: ## Install all dependencies
	@echo "$(GREEN)Installing dependencies...$(RESET)"
	composer install --no-interaction --prefer-dist
	@echo "$(GREEN)✅ Dependencies installed!$(RESET)"

update: ## Update dependencies
	@echo "$(GREEN)Updating dependencies...$(RESET)"
	composer update --no-interaction
	@echo "$(GREEN)✅ Dependencies updated!$(RESET)"

##
## 🧪 Testing
## ----------

test: ## Run all PHPUnit tests
	@echo "$(GREEN)Running PHPUnit tests...$(RESET)"
	vendor/bin/phpunit

test-unit: ## Run unit tests only
	@echo "$(GREEN)Running unit tests...$(RESET)"
	vendor/bin/phpunit --testsuite Unit

test-integration: ## Run integration tests only
	@echo "$(GREEN)Running integration tests...$(RESET)"
	vendor/bin/phpunit --testsuite Integration

test-performance: ## Run performance tests only
	@echo "$(GREEN)Running performance tests...$(RESET)"
	vendor/bin/phpunit --testsuite Performance

test-security: ## Run security tests only
	@echo "$(GREEN)Running security tests...$(RESET)"
	vendor/bin/phpunit --testsuite Security

coverage: ## Generate code coverage report (HTML)
	@echo "$(GREEN)Generating coverage report...$(RESET)"
	vendor/bin/phpunit --coverage-html coverage/
	@echo "$(GREEN)✅ Coverage report: coverage/index.html$(RESET)"

coverage-text: ## Show code coverage in terminal
	@echo "$(GREEN)Generating coverage report...$(RESET)"
	vendor/bin/phpunit --coverage-text

##
## 🔍 Static Analysis
## ------------------

stan: ## Run PHPStan static analysis (Level 9)
	@echo "$(GREEN)Running PHPStan (Level 9)...$(RESET)"
	@if [ ! -f vendor/bin/phpstan ]; then \
		echo "$(YELLOW)Installing PHPStan...$(RESET)"; \
		composer require --dev phpstan/phpstan:^1.10 --no-interaction; \
	fi
	vendor/bin/phpstan analyse --memory-limit=512M

stan-baseline: ## Generate PHPStan baseline
	@echo "$(GREEN)Generating PHPStan baseline...$(RESET)"
	vendor/bin/phpstan analyse --generate-baseline --memory-limit=512M
	@echo "$(GREEN)✅ Baseline saved to phpstan-baseline.neon$(RESET)"

##
## 🎨 Code Style
## -------------

cs-fix: ## Fix code style issues (PSR-12)
	@echo "$(GREEN)Fixing code style...$(RESET)"
	@if [ ! -f vendor/bin/php-cs-fixer ]; then \
		echo "$(YELLOW)Installing PHP CS Fixer...$(RESET)"; \
		composer require --dev friendsofphp/php-cs-fixer:^3.0 --no-interaction; \
	fi
	vendor/bin/php-cs-fixer fix
	@echo "$(GREEN)✅ Code style fixed!$(RESET)"

cs-check: ## Check code style (dry-run)
	@echo "$(GREEN)Checking code style...$(RESET)"
	@if [ ! -f vendor/bin/php-cs-fixer ]; then \
		echo "$(YELLOW)Installing PHP CS Fixer...$(RESET)"; \
		composer require --dev friendsofphp/php-cs-fixer:^3.0 --no-interaction; \
	fi
	vendor/bin/php-cs-fixer fix --dry-run --diff

##
## 🦠 Mutation Testing
## -------------------

mutation: ## Run mutation testing (85%+ MSI target)
	@echo "$(GREEN)Running mutation testing...$(RESET)"
	@if [ ! -f vendor/bin/infection ]; then \
		echo "$(YELLOW)Installing Infection...$(RESET)"; \
		composer require --dev infection/infection:^0.27 --no-interaction; \
	fi
	vendor/bin/infection --threads=4 --min-msi=85 --min-covered-msi=90
	@echo "$(GREEN)✅ Check infection.log for details$(RESET)"

mutation-baseline: ## Generate mutation testing baseline
	@echo "$(GREEN)Generating mutation baseline...$(RESET)"
	vendor/bin/infection --threads=4 --min-msi=0
	@echo "$(GREEN)✅ Baseline generated$(RESET)"

##
## 🔒 Security
## -----------

security: ## Run security audit
	@echo "$(GREEN)Running security audit...$(RESET)"
	composer audit
	@echo "$(GREEN)✅ Security audit complete$(RESET)"

##
## 🚦 CI/CD
## --------

ci: ## Run full CI pipeline locally
	@echo "$(GREEN)======================================$(RESET)"
	@echo "$(GREEN)  RUNNING FULL CI PIPELINE LOCALLY   $(RESET)"
	@echo "$(GREEN)======================================$(RESET)"
	@echo ""
	@make test
	@echo ""
	@make stan
	@echo ""
	@make cs-check
	@echo ""
	@make security
	@echo ""
	@echo "$(GREEN)======================================$(RESET)"
	@echo "$(GREEN)  ✅ CI PIPELINE PASSED!              $(RESET)"
	@echo "$(GREEN)======================================$(RESET)"

pre-commit: ## Run pre-commit checks
	@echo "$(GREEN)Running pre-commit checks...$(RESET)"
	@make cs-fix
	@make test-unit
	@make stan
	@echo "$(GREEN)✅ Pre-commit checks passed!$(RESET)"

pre-push: ## Run pre-push checks (full validation)
	@echo "$(GREEN)Running pre-push checks...$(RESET)"
	@make ci
	@echo "$(GREEN)✅ Ready to push!$(RESET)"

##
## 🧹 Cleanup
## ----------

clean: ## Clean cache and build files
	@echo "$(GREEN)Cleaning cache and build files...$(RESET)"
	rm -rf coverage/
	rm -rf build/
	rm -rf .phpunit.cache/
	rm -f .php-cs-fixer.cache
	rm -f infection.log infection-summary.log infection.json
	@echo "$(GREEN)✅ Cleaned!$(RESET)"

clean-all: clean ## Clean everything (including vendor)
	@echo "$(GREEN)Removing vendor directory...$(RESET)"
	rm -rf vendor/
	@echo "$(GREEN)✅ Everything cleaned!$(RESET)"

##
## 📊 Reports
## ----------

report: coverage ## Generate all quality reports
	@echo "$(GREEN)Generating quality reports...$(RESET)"
	@make coverage
	@make stan > stan-report.txt 2>&1 || true
	@echo "$(GREEN)✅ Reports generated:$(RESET)"
	@echo "  - coverage/index.html"
	@echo "  - stan-report.txt"

##
## 🎯 Quick Commands
## -----------------

quick: test-unit stan ## Quick validation (unit tests + static analysis)
	@echo "$(GREEN)✅ Quick validation passed!$(RESET)"

full: ci mutation ## Full validation (CI + mutation testing)
	@echo "$(GREEN)✅ Full validation passed!$(RESET)"
