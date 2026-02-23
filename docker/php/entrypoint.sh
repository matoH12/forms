#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Wait for MySQL to be ready
wait_for_mysql() {
    log_info "Waiting for MySQL to be ready..."

    local max_attempts=30
    local attempt=1

    while [ $attempt -le $max_attempts ]; do
        if php -r "
            \$host = getenv('DB_HOST') ?: 'mysql';
            \$port = getenv('DB_PORT') ?: '3306';
            \$user = getenv('DB_USERNAME') ?: 'formulare';
            \$pass = getenv('DB_PASSWORD') ?: 'secret';
            \$db = getenv('DB_DATABASE') ?: 'formulare';

            try {
                \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass, [
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                exit(0);
            } catch (Exception \$e) {
                exit(1);
            }
        " 2>/dev/null; then
            log_info "MySQL is ready!"
            return 0
        fi

        log_warn "MySQL not ready yet (attempt $attempt/$max_attempts)..."
        sleep 2
        attempt=$((attempt + 1))
    done

    log_error "MySQL did not become ready in time"
    return 1
}

# Check if migrations table exists (indicates if DB is initialized)
check_migrations_table() {
    php -r "
        \$host = getenv('DB_HOST') ?: 'mysql';
        \$port = getenv('DB_PORT') ?: '3306';
        \$user = getenv('DB_USERNAME') ?: 'formulare';
        \$pass = getenv('DB_PASSWORD') ?: 'secret';
        \$db = getenv('DB_DATABASE') ?: 'formulare';

        try {
            \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
            \$result = \$pdo->query(\"SHOW TABLES LIKE 'migrations'\");
            exit(\$result->rowCount() > 0 ? 0 : 1);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null
}

# Check if there are pending migrations
check_pending_migrations() {
    local pending=$(php artisan migrate:status --no-interaction 2>/dev/null | grep -c "Pending" || true)
    [ "${pending:-0}" -gt 0 ]
}

# Run database initialization
init_database() {
    log_info "Checking database status..."

    if ! check_migrations_table; then
        log_info "No migrations table found. Running initial migration..."
        php artisan migrate --force --no-interaction

        # Run seeders for fresh install
        if [ "${RUN_SEEDERS:-false}" = "true" ]; then
            log_info "Running database seeders..."
            php artisan db:seed --force --no-interaction
        fi
    else
        log_info "Checking for pending migrations..."
        if check_pending_migrations; then
            log_info "Pending migrations found. Running migrations..."
            php artisan migrate --force --no-interaction
        else
            log_info "Database is up to date."
        fi
    fi

    # Always run EmailTemplateSeeder to ensure system templates exist
    # Uses updateOrCreate so it's safe to run multiple times
    log_info "Ensuring email templates are up to date..."
    php artisan db:seed --class=EmailTemplateSeeder --force --no-interaction 2>/dev/null || log_warn "EmailTemplateSeeder failed (non-critical)"
}

# Generate app key if not set
ensure_app_key() {
    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:placeholder" ]; then
        if [ -f .env ] && grep -q "^APP_KEY=$" .env; then
            log_info "Generating application key..."
            php artisan key:generate --force --no-interaction
        fi
    fi
}

# Create storage symlink
create_storage_link() {
    if [ ! -L public/storage ]; then
        log_info "Creating storage symlink..."
        php artisan storage:link --no-interaction 2>/dev/null || true
    fi
}

# Initialize public files in volume (copies from image if volume is empty or outdated)
init_public_files() {
    if [ -d "/var/www/html/public-src" ]; then
        # Check if public volume has index.php
        if [ ! -f "/var/www/html/public/index.php" ]; then
            log_info "Public volume is empty, initializing all files..."
            cp -r /var/www/html/public-src/* /var/www/html/public/ 2>/dev/null || true
        else
            # Always sync build directory to ensure frontend updates are deployed
            log_info "Syncing build files to public volume..."
            rm -rf /var/www/html/public/build 2>/dev/null || true
            if [ -d "/var/www/html/public-src/build" ]; then
                cp -r /var/www/html/public-src/build /var/www/html/public/ 2>/dev/null || true
            fi
            # Also sync other static assets that might have changed
            cp /var/www/html/public-src/*.php /var/www/html/public/ 2>/dev/null || true
            cp /var/www/html/public-src/*.ico /var/www/html/public/ 2>/dev/null || true
            cp /var/www/html/public-src/robots.txt /var/www/html/public/ 2>/dev/null || true
        fi
        log_info "Public files synchronized."
    fi
}

# Cache configuration for production
cache_config() {
    if [ "${APP_ENV:-production}" = "production" ]; then
        log_info "Caching configuration for production..."
        php artisan config:cache --no-interaction 2>/dev/null || true
        php artisan route:cache --no-interaction 2>/dev/null || true
        php artisan view:cache --no-interaction 2>/dev/null || true
    else
        log_info "Development mode - clearing caches..."
        php artisan config:clear --no-interaction 2>/dev/null || true
        php artisan route:clear --no-interaction 2>/dev/null || true
        php artisan view:clear --no-interaction 2>/dev/null || true
    fi
}

# Generate Swagger/OpenAPI documentation
generate_swagger_docs() {
    log_info "Generating Swagger API documentation..."
    php artisan l5-swagger:generate --no-interaction 2>/dev/null || log_warn "Swagger generation failed (non-critical)"
}

# Ensure proper permissions on storage
fix_permissions() {
    log_info "Setting storage permissions..."
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
}

# Main initialization
main() {
    log_info "Starting application initialization..."

    # Fix permissions first
    fix_permissions

    # Wait for database
    wait_for_mysql

    # Ensure app key exists
    ensure_app_key

    # Run database migrations
    init_database

    # Create storage link
    create_storage_link

    # Initialize public files in volume
    init_public_files

    # Cache config
    cache_config

    # Generate Swagger docs
    generate_swagger_docs

    log_info "Initialization complete!"

    # Execute the main command
    exec "$@"
}

# Run main function with passed arguments
main "$@"
