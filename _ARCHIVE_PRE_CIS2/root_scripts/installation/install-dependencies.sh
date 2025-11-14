#!/bin/bash
# Composer Dependency Installation Script
# Run this to ensure all modules have dependencies installed

echo "🚀 Installing CIS Module Dependencies..."
echo ""

# Root modules
echo "📦 Installing root module dependencies..."
cd /home/master/applications/jcepnzzkmj/public_html/modules
composer install --no-dev --optimize-autoloader
echo "✅ Root modules complete"
echo ""

# Consignments module
if [ -d "consignments" ]; then
    echo "📦 Installing consignments dependencies..."
    cd consignments
    composer install --no-dev --optimize-autoloader
    cd ..
    echo "✅ Consignments complete"
    echo ""
fi

# Payroll module
if [ -d "human_resources/payroll" ]; then
    echo "📦 Installing payroll dependencies..."
    cd human_resources/payroll
    composer install --no-dev --optimize-autoloader
    cd ../..
    echo "✅ Payroll complete"
    echo ""
fi

# Base module
if [ -d "base" ]; then
    echo "📦 Installing base dependencies..."
    cd base
    if [ -f "composer.json" ]; then
        composer install --no-dev --optimize-autoloader
    fi
    cd ..
    echo "✅ Base complete"
    echo ""
fi

echo "🎉 All dependencies installed!"
echo ""
echo "📊 Summary:"
composer show --installed --working-dir=/home/master/applications/jcepnzzkmj/public_html/modules | head -20
