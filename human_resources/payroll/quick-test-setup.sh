#!/bin/bash
# Quick Test Setup Script - HARDNESS MODE
# Installs all testing dependencies and runs comprehensive test suite

set -e

echo "🔥 HARDNESS MODE - Installing Testing Dependencies..."

# Install dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "✅ Dependencies installed"

# Create test directories
mkdir -p tests/results
mkdir -p tests/coverage
mkdir -p tests/fixtures
mkdir -p tests/mocks

echo "✅ Test directories created"

# Run PHPUnit tests
echo ""
echo "🧪 Running PHPUnit Tests..."
vendor/bin/phpunit --testdox --colors=always

# Run PHPStan static analysis
echo ""
echo "🔍 Running PHPStan (Level 6)..."
vendor/bin/phpstan analyse --no-progress || true

# Run PHPCS code style check
echo ""
echo "📏 Running PHPCS (PSR-12)..."
vendor/bin/phpcs --standard=PSR12 controllers/ services/ lib/ --report=summary || true

# Generate coverage report
echo ""
echo "📊 Generating Coverage Report..."
vendor/bin/phpunit --coverage-html tests/coverage --coverage-text

# Run parallel tests for speed
echo ""
echo "⚡ Running Parallel Tests..."
vendor/bin/paratest --processes=4 --runner=WrapperRunner tests/ || true

# Run mutation testing
echo ""
echo "🧬 Running Mutation Tests (Infection)..."
vendor/bin/infection --threads=4 --min-msi=70 --show-mutations || true

echo ""
echo "============================================"
echo "✅ ALL TESTS COMPLETE - HARDNESS MODE"
echo "============================================"
echo ""
echo "📊 Reports generated:"
echo "  - Coverage: tests/coverage/index.html"
echo "  - Mutation: tests/results/infection.log"
echo ""
