#!/bin/bash
set -e

echo "=========================================="
echo "Generating Code Coverage Report"
echo "=========================================="

# Check if WordPress container is running
if ! docker compose ps wpcli | grep -q "Up"; then
    echo ""
    echo "❌ wpcli container is not running"
    echo "Please start the Docker environment first: ./docker/setup.sh"
    exit 1
fi

echo ""
echo "[1/2] Verifying PCOV is available..."
docker compose exec -T wpcli php -m | grep -q pcov
if [ $? -eq 0 ]; then
    echo "✓ PCOV is available"
else
    echo "❌ PCOV is not available. Please rebuild the wpcli container:"
    echo "  docker compose build wpcli"
    exit 1
fi

echo ""
echo "[2/2] Running tests with coverage..."
docker compose exec -T wpcli bash -c "
    cd /var/www/html/wp-content/plugins/wp-resta
    php -d pcov.enabled=1 vendor/bin/phpunit --coverage-html coverage/html --coverage-text
"

RESULT=$?

if [ $RESULT -eq 0 ]; then
    echo ""
    echo "=========================================="
    echo "✓ Coverage report generated!"
    echo "=========================================="
    echo ""
    echo "📊 Coverage report:"
    echo "  Open: coverage/html/index.html"
    echo ""
else
    echo ""
    echo "=========================================="
    echo "❌ Coverage generation failed"
    echo "=========================================="
    exit $RESULT
fi
