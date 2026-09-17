#!/bin/bash

###############################################################################
# Kong API Gateway Startup and Configuration Script
# Initializes Kong with all services, routes, and plugins
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
KONG_ADMIN_URL="${KONG_ADMIN_URL:-http://kong:8001}"
KONG_PROXY_URL="${KONG_PROXY_URL:-http://kong:8000}"
MAX_RETRIES=30
RETRY_DELAY=2

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

###############################################################################
# Wait for Kong to be ready
###############################################################################
wait_for_kong() {
    log_info "Waiting for Kong Admin API to be ready at ${KONG_ADMIN_URL}..."

    for i in $(seq 1 $MAX_RETRIES); do
        if curl -s -f "${KONG_ADMIN_URL}/status" > /dev/null 2>&1; then
            log_success "Kong Admin API is ready"
            return 0
        fi

        if [ $i -lt $MAX_RETRIES ]; then
            log_warning "Kong not ready yet. Retrying in ${RETRY_DELAY}s... (${i}/${MAX_RETRIES})"
            sleep $RETRY_DELAY
        fi
    done

    log_error "Kong Admin API failed to start after ${MAX_RETRIES} retries"
    return 1
}

###############################################################################
# Wait for microservices to be ready
###############################################################################
wait_for_services() {
    log_info "Waiting for microservices to be ready..."

    local services=(
        "invoice-service:8001"
        "expense-service:8002"
        "product-service:8003"
        "customer-service:8004"
        "settings-service:8005"
        "transport-service:8006"
    )

    for service in "${services[@]}"; do
        local name="${service%%:*}"
        local port="${service##*:}"

        log_info "Checking $name on port $port..."

        for i in $(seq 1 $MAX_RETRIES); do
            if timeout 2 bash -c "echo >/dev/tcp/$name/$port" 2>/dev/null; then
                log_success "$name is ready"
                break
            fi

            if [ $i -lt $MAX_RETRIES ]; then
                log_warning "$name not ready. Retrying in ${RETRY_DELAY}s... (${i}/${MAX_RETRIES})"
                sleep $RETRY_DELAY
            else
                log_warning "$name did not respond within timeout (this may be OK if running locally)"
            fi
        done
    done
}

###############################################################################
# Load Kong configuration from YAML
###############################################################################
load_kong_yaml_config() {
    log_info "Loading Kong configuration from kong.yml..."

    if [ ! -f "kong.yml" ]; then
        log_error "kong.yml not found in current directory"
        return 1
    fi

    # Use Kong's declarative configuration loader
    # This assumes Kong is running with declarative config support
    curl -s -X POST "${KONG_ADMIN_URL}/config" \
        -H "Content-Type: application/yaml" \
        -d @kong.yml > /dev/null 2>&1

    if [ $? -eq 0 ]; then
        log_success "Kong configuration loaded successfully"
        return 0
    else
        log_warning "Could not load declarative config. Falling back to API-based configuration."
        return 1
    fi
}

###############################################################################
# Create Services via API
###############################################################################
create_service() {
    local name=$1
    local url=$2
    local tags=$3

    log_info "Creating service: $name"

    # Check if service already exists
    if curl -s -f "${KONG_ADMIN_URL}/services/${name}" > /dev/null 2>&1; then
        log_warning "Service $name already exists. Skipping creation."
        return 0
    fi

    local tags_json=""
    if [ -n "$tags" ]; then
        tags_json=", \"tags\": [\"${tags//,/\", \"}\"]"
    fi

    curl -s -X POST "${KONG_ADMIN_URL}/services" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"${name}\",
            \"url\": \"${url}\",
            \"connect_timeout\": 6000,
            \"write_timeout\": 60000,
            \"read_timeout\": 60000,
            \"retries\": 3${tags_json}
        }" > /dev/null 2>&1

    if [ $? -eq 0 ]; then
        log_success "Service $name created"
    else
        log_error "Failed to create service $name"
        return 1
    fi
}

###############################################################################
# Create Routes
###############################################################################
create_route() {
    local service=$1
    local route_name=$2
    local paths=$3
    local methods=$4

    log_info "Creating route: $route_name (Service: $service)"

    # Convert paths string to JSON array
    local paths_json="[\"${paths//|/\", \"}\"]"

    # Convert methods string to JSON array
    local methods_json="[\"${methods//|/\", \"}\"]"

    curl -s -X POST "${KONG_ADMIN_URL}/services/${service}/routes" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"${route_name}\",
            \"paths\": ${paths_json},
            \"methods\": ${methods_json},
            \"strip_path\": false,
            \"preserve_host\": true
        }" > /dev/null 2>&1

    if [ $? -eq 0 ]; then
        log_success "Route $route_name created"
    else
        log_warning "Route $route_name may already exist or could not be created"
    fi
}

###############################################################################
# Enable Plugin
###############################################################################
enable_plugin() {
    local plugin_name=$1
    local scope=$2
    local scope_id=$3

    log_info "Enabling plugin: $plugin_name (Scope: $scope/$scope_id)"

    local endpoint="${KONG_ADMIN_URL}/plugins"

    case $scope in
        global)
            # Check if plugin already exists
            if curl -s "${KONG_ADMIN_URL}/plugins" | grep -q "$plugin_name"; then
                log_warning "Plugin $plugin_name already exists. Skipping."
                return 0
            fi
            ;;
        service)
            endpoint="${KONG_ADMIN_URL}/services/${scope_id}/plugins"
            ;;
        route)
            # For route-scoped plugins, need service and route info
            endpoint="${KONG_ADMIN_URL}/routes/${scope_id}/plugins"
            ;;
    esac

    # Note: Actual plugin configuration is handled by declarative config
    # This is just a placeholder for reference
    return 0
}

###############################################################################
# Configure Consumers and Credentials
###############################################################################
setup_consumers() {
    log_info "Setting up API consumers and credentials..."

    # Create example consumer for invoice service
    local consumer_name="invoice-client"

    if ! curl -s -f "${KONG_ADMIN_URL}/consumers/${consumer_name}" > /dev/null 2>&1; then
        log_info "Creating consumer: $consumer_name"

        curl -s -X POST "${KONG_ADMIN_URL}/consumers" \
            -H "Content-Type: application/json" \
            -d "{
                \"username\": \"${consumer_name}\",
                \"custom_id\": \"${consumer_name}-001\"
            }" > /dev/null 2>&1

        log_success "Consumer $consumer_name created"

        # Add API key credential
        curl -s -X POST "${KONG_ADMIN_URL}/consumers/${consumer_name}/key-auth" \
            -H "Content-Type: application/json" \
            -d "{
                \"key\": \"invoice-api-key-dev-$(date +%s)\"
            }" > /dev/null 2>&1

        log_success "API key created for $consumer_name"
    else
        log_warning "Consumer $consumer_name already exists"
    fi
}

###############################################################################
# Health Check
###############################################################################
health_check() {
    log_info "Performing health checks..."

    # Check Kong status
    if curl -s -f "${KONG_ADMIN_URL}/status" > /dev/null 2>&1; then
        log_success "Kong is healthy"
    else
        log_error "Kong health check failed"
        return 1
    fi

    # Check services
    local services_count=$(curl -s "${KONG_ADMIN_URL}/services" | grep -o '"name"' | wc -l)
    log_info "Configured services: $services_count"

    # Check routes
    local routes_count=$(curl -s "${KONG_ADMIN_URL}/routes" | grep -o '"name"' | wc -l)
    log_info "Configured routes: $routes_count"

    # Check plugins
    local plugins_count=$(curl -s "${KONG_ADMIN_URL}/plugins" | grep -o '"name"' | wc -l)
    log_info "Enabled plugins: $plugins_count"

    log_success "Health checks completed"
}

###############################################################################
# Main Setup Routine
###############################################################################
main() {
    log_info "==================================================="
    log_info "Kong API Gateway Setup & Configuration Script"
    log_info "==================================================="
    log_info "Kong Admin URL: ${KONG_ADMIN_URL}"
    log_info "Kong Proxy URL: ${KONG_PROXY_URL}"
    log_info ""

    # Step 1: Wait for Kong to start
    log_info "Step 1: Waiting for Kong..."
    wait_for_kong || exit 1

    # Step 2: Wait for microservices
    log_info "Step 2: Waiting for microservices..."
    wait_for_services

    # Step 3: Load Kong configuration
    log_info "Step 3: Loading Kong configuration..."
    if load_kong_yaml_config; then
        log_success "Declarative configuration loaded successfully"
    else
        log_warning "Declarative configuration failed. Using API-based configuration..."

        # Fall back to API-based configuration
        # (in production, would create services, routes, and plugins via REST API)
        create_service "invoice-service" "http://invoice-service:8001/api/v1" "domain:invoice,ddd"
        create_service "expense-service" "http://expense-service:8002/api/v1" "domain:expense,ddd"
        create_service "product-service" "http://product-service:8003/api/v1" "domain:product,ddd"
        create_service "customer-service" "http://customer-service:8004/api/v1" "domain:customer,ddd"
        create_service "settings-service" "http://settings-service:8005/api/v1" "domain:settings,ddd"
        create_service "transport-service" "http://transport-service:8006/api/v1" "domain:transport,ddd"
    fi

    # Step 4: Setup consumers and credentials
    log_info "Step 4: Setting up consumers and credentials..."
    setup_consumers

    # Step 5: Health checks
    log_info "Step 5: Running health checks..."
    health_check

    log_info ""
    log_success "==================================================="
    log_success "Kong API Gateway is ready!"
    log_success "==================================================="
    log_info ""
    log_info "Admin API: ${KONG_ADMIN_URL}"
    log_info "Admin GUI: http://localhost:8002"
    log_info "Proxy URL: ${KONG_PROXY_URL}"
    log_info "Konga GUI: http://localhost:1337"
    log_info ""
    log_success "API Gateway endpoints:"
    log_info "  Invoices:   ${KONG_PROXY_URL}/api/v1/invoices"
    log_info "  Expenses:   ${KONG_PROXY_URL}/api/v1/expenses"
    log_info "  Products:   ${KONG_PROXY_URL}/api/v1/products"
    log_info "  Customers:  ${KONG_PROXY_URL}/api/v1/customers"
    log_info "  Settings:   ${KONG_PROXY_URL}/api/v1/settings"
    log_info "  Transport:  ${KONG_PROXY_URL}/api/v1/transport"
    log_info "  Health:     ${KONG_PROXY_URL}/health"
    log_info ""
}

# Run main function
main "$@"
