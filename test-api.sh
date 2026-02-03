#!/bin/bash

# ERP API Testing Script

echo "🚀 Starting ERP API Tests..."

# Navigate to backend directory
cd backend

# Run all tests
echo "🧪 Running all API tests..."
php artisan test --testsuite=Feature

# Run specific test suites
echo "📋 Running authentication tests..."
php artisan test tests/Feature/AuthApiTest.php

echo "📦 Running product tests..."
php artisan test tests/Feature/ProductApiTest.php

echo "🏷️ Running category tests..."
php artisan test tests/Feature/CategoryApiTest.php

echo "👤 Running user tests..."
php artisan test tests/Feature/UserApiTest.php

# Generate coverage report
echo "📊 Generating test coverage report..."
php artisan test --coverage --min=80

echo "✅ API testing completed!"