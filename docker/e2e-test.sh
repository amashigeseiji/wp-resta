#!/bin/bash
set -e

echo "=========================================="
echo "Running E2E Tests"
echo "=========================================="

# Check if WordPress container is running
if ! docker compose ps wordpress | grep -q "Up"; then
    echo ""
    echo "❌ WordPress container is not running"
    echo ""
    echo "Please start the Docker environment first:"
    echo "  ./docker/setup.sh"
    echo ""
    exit 1
fi

echo ""
echo "Environment:"
echo "  WordPress: http://localhost:8080"
echo "  Container: wp-resta-wpcli"
echo ""

# Run E2E tests in wpcli container
echo "Running PHPUnit E2E tests in wpcli container..."
echo ""

docker compose exec -T wpcli bash -c "
    cd /var/www/html/wp-content/plugins/wp-resta &&
    php vendor/bin/phpunit --configuration phpunit-e2e.xml --testdox
"

RESULT=$?

echo ""
if [ $RESULT -eq 0 ]; then
    echo "=========================================="
    echo "✓ All E2E tests passed!"
    echo "=========================================="
else
    echo "=========================================="
    echo "❌ Some E2E tests failed"
    echo "=========================================="
fi

exit $RESULT
