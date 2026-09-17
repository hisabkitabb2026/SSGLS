#!/bin/bash

###############################################################################
# Kong API Gateway - Complete Startup Script
# Manages Kong, microservices, and monitoring stack
# Usage: ./start-kong-gateway.sh [start|stop|restart|logs|status]
###############################################################################

set -e

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Log functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_header() {
    echo -e "\n${MAGENTA}════════════════════════════════════════════════════════${NC}"
    echo -e "${MAGENTA}$1${NC}"
    echo -e "${MAGENTA}════════════════════════════════════════════════════════${NC}\n"
}

# Configuration
COMPOSE_FILES="-f ${PROJECT_ROOT}/docker-compose.yml -f ${PROJECT_ROOT}/docker-compose.kong.yml -f ${PROJECT_ROOT}/docker-compose.microservices.yml"
KONG_ADMIN_URL="${KONG_ADMIN_URL:-http://localhost:8001}"
KONG_PROXY_URL="${KONG_PROXY_URL:-http://localhost:8000}"

###############################################################################
# HELP
###############################################################################
show_help() {
    cat << EOF
${CYAN}Kong API Gateway Control Script${NC}

${YELLOW}Usage:${NC}
    $0 [command] [options]

${YELLOW}Commands:${NC}
    start               Start Kong and microservices
    stop                Stop Kong and microservices
    restart             Restart Kong and microservices
    logs [service]      View logs (optional: specify service)
    status              Check status of all services
    health              Run health checks
    load-config         Load declarative kong.yml configuration
    reset               Reset Kong database (destructive!)
    scale [service]     Scale a service (e.g., scale invoice-service 3)
    info                Display system information
    dashboard           Open Kong Admin Dashboard URLs
    help                Show this help message

${YELLOW}Examples:${NC}
    # Start all services
    $0 start

    # View Kong logs
    $0 logs kong

    # Check health of all services
    $0 health

    # Scale invoice service to 3 replicas
    $0 scale invoice-service 3

EOF
}

###############################################################################
# START - Initialize Kong and all services
###############################################################################
start_services() {
    log_header "Starting Kong API Gateway & Microservices"

    log_info "Starting containers..."
    cd "$PROJECT_ROOT"
    docker-compose $COMPOSE_FILES up -d

    if [ $? -ne 0 ]; then
        log_error "Failed to start containers"
        return 1
    fi

    log_success "Containers started"
    sleep 5

    log_info "Waiting for Kong to be ready..."
    local max_retries=30
    local count=0

    while [ $count -lt $max_retries ]; do
        if curl -s -f "${KONG_ADMIN_URL}/status" > /dev/null 2>&1; then
            log_success "Kong Admin API is ready"
            break
        fi
        count=$((count + 1))
        if [ $count -lt $max_retries ]; then
            log_warning "Waiting... ($count/$max_retries)"
            sleep 2
        fi
    done

    if [ $count -eq $max_retries ]; then
        log_error "Kong failed to start"
        return 1
    fi

    # Load Kong configuration
    log_info "Loading Kong configuration from kong.yml..."
    if cd "$PROJECT_ROOT" && bash "$SCRIPT_DIR/kong-startup.sh"; then
        log_success "Kong configuration loaded successfully"
    else
        log_warning "Kong configuration load had issues (check logs)"
    fi

    log_header "Kong API Gateway is Ready!"
    show_urls
}

###############################################################################
# STOP - Shutdown all services
###############################################################################
stop_services() {
    log_header "Stopping Kong API Gateway & Microservices"

    cd "$PROJECT_ROOT"
    docker-compose $COMPOSE_FILES down

    if [ $? -eq 0 ]; then
        log_success "All services stopped"
    else
        log_error "Failed to stop services"
        return 1
    fi
}

###############################################################################
# RESTART - Restart services
###############################################################################
restart_services() {
    log_header "Restarting Kong API Gateway & Microservices"

    stop_services
    sleep 2
    start_services
}

###############################################################################
# LOGS - View service logs
###############################################################################
view_logs() {
    local service=$1

    cd "$PROJECT_ROOT"

    if [ -z "$service" ]; then
        log_info "Showing logs for all services..."
        docker-compose $COMPOSE_FILES logs -f --tail=50
    else
        log_info "Showing logs for $service..."
        docker-compose $COMPOSE_FILES logs -f --tail=100 "$service"
    fi
}

###############################################################################
# STATUS - Check service status
###############################################################################
check_status() {
    log_header "Service Status"

    cd "$PROJECT_ROOT"

    local services=(
        "kong-db"
        "kong-migrations"
        "kong"
        "redis"
        "konga"
        "prometheus"
        "grafana"
        "jaeger"
        "elasticsearch"
        "kibana"
        "invoice-service"
        "expense-service"
        "product-service"
        "customer-service"
        "settings-service"
        "transport-service"
        "logging-aggregator"
    )

    for service in "${services[@]}"; do
        local status=$(docker-compose $COMPOSE_FILES ps "$service" 2>/dev/null | tail -1)
        if echo "$status" | grep -q "Up"; then
            echo -e "${GREEN}✓${NC} $service - Running"
        elif echo "$status" | grep -q "Exited"; then
            echo -e "${RED}✗${NC} $service - Stopped"
        else
            echo -e "${YELLOW}?${NC} $service - Unknown"
        fi
    done
}

###############################################################################
# HEALTH - Perform health checks
###############################################################################
health_checks() {
    log_header "Running Health Checks"

    log_info "Kong Admin API..."
    if curl -s -f "${KONG_ADMIN_URL}/status" > /dev/null 2>&1; then
        log_success "Kong Admin API is healthy"
    else
        log_error "Kong Admin API is unreachable"
    fi

    log_info "Kong Proxy..."
    if curl -s -f "${KONG_PROXY_URL}/health" > /dev/null 2>&1; then
        log_success "Kong Proxy is healthy"
    else
        log_warning "Kong Proxy health endpoint unreachable"
    fi

    # Check microservices
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

        log_info "Checking $name..."
        if timeout 2 bash -c "echo >/dev/tcp/localhost/$port" 2>/dev/null; then
            log_success "$name is reachable"
        else
            log_warning "$name is not responding"
        fi
    done

    # Check Kong services and routes
    log_info "Kong Services..."
    local services_count=$(curl -s "${KONG_ADMIN_URL}/services" | grep -o '"name"' | wc -l)
    log_success "Services configured: $services_count"

    log_info "Kong Routes..."
    local routes_count=$(curl -s "${KONG_ADMIN_URL}/routes" | grep -o '"name"' | wc -l)
    log_success "Routes configured: $routes_count"

    log_info "Kong Plugins..."
    local plugins_count=$(curl -s "${KONG_ADMIN_URL}/plugins" | grep -o '"name"' | wc -l)
    log_success "Plugins enabled: $plugins_count"
}

###############################################################################
# LOAD CONFIG - Reload Kong configuration
###############################################################################
load_config() {
    log_header "Loading Kong Configuration"

    if [ ! -f "$PROJECT_ROOT/kong.yml" ]; then
        log_error "kong.yml not found"
        return 1
    fi

    log_info "Sending declarative configuration to Kong..."

    local response=$(curl -s -X POST "${KONG_ADMIN_URL}/config" \
        -H "Content-Type: application/yaml" \
        -d @"$PROJECT_ROOT/kong.yml")

    if echo "$response" | grep -q "error"; then
        log_error "Configuration load failed"
        echo "$response" | jq . 2>/dev/null || echo "$response"
        return 1
    fi

    log_success "Kong configuration reloaded successfully"
}

###############################################################################
# RESET - Reset Kong database
###############################################################################
reset_kong() {
    log_warning "This will delete all Kong configuration!"
    read -p "Are you sure? (yes/no): " -r
    echo

    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        log_info "Reset cancelled"
        return 0
    fi

    log_header "Resetting Kong"

    cd "$PROJECT_ROOT"
    docker-compose $COMPOSE_FILES exec -T kong-db psql -U kong -d kong -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;"

    log_info "Running migrations..."
    docker-compose $COMPOSE_FILES exec -T kong kong migrations bootstrap

    log_success "Kong reset complete"
}

###############################################################################
# SCALE - Scale a service
###############################################################################
scale_service() {
    local service=$1
    local replicas=$2

    if [ -z "$service" ] || [ -z "$replicas" ]; then
        log_error "Usage: $0 scale [service] [replicas]"
        return 1
    fi

    log_header "Scaling $service to $replicas replicas"

    cd "$PROJECT_ROOT"
    docker-compose $COMPOSE_FILES up -d --scale "$service=$replicas"

    if [ $? -eq 0 ]; then
        log_success "Service scaled successfully"
    else
        log_error "Failed to scale service"
        return 1
    fi
}

###############################################################################
# INFO - Display system information
###############################################################################
show_info() {
    log_header "Kong API Gateway Information"

    echo -e "${CYAN}System Info:${NC}"
    echo "  Docker Version: $(docker --version)"
    echo "  Docker Compose: $(docker-compose --version)"
    echo ""

    echo -e "${CYAN}Kong Configuration:${NC}"
    echo "  Admin API: ${KONG_ADMIN_URL}"
    echo "  Proxy URL: ${KONG_PROXY_URL}"
    echo "  Kong Version: $(docker exec invoiceshelf-kong kong version 2>/dev/null || echo 'Unknown')"
    echo ""

    echo -e "${CYAN}Microservices Ports:${NC}"
    echo "  Invoice Service:   8001"
    echo "  Expense Service:   8002"
    echo "  Product Service:   8003"
    echo "  Customer Service:  8004"
    echo "  Settings Service:  8005"
    echo "  Transport Service: 8006"
    echo ""

    echo -e "${CYAN}Monitoring & Tools:${NC}"
    echo "  Kong Admin GUI:    http://localhost:8002"
    echo "  Konga Dashboard:   http://localhost:1337"
    echo "  Prometheus:        http://localhost:9090"
    echo "  Grafana:           http://localhost:3000"
    echo "  Kibana:            http://localhost:5601"
    echo "  Jaeger Tracing:    http://localhost:16686"
    echo ""
}

###############################################################################
# SHOW URLS
###############################################################################
show_urls() {
    echo -e "\n${GREEN}════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}Kong API Gateway URLs${NC}"
    echo -e "${GREEN}════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "${CYAN}API Gateway:${NC}"
    echo -e "  Proxy:    ${YELLOW}${KONG_PROXY_URL}${NC}"
    echo -e "  Admin:    ${YELLOW}${KONG_ADMIN_URL}${NC}"
    echo ""
    echo -e "${CYAN}Microservices:${NC}"
    echo -e "  Invoices:   ${YELLOW}${KONG_PROXY_URL}/api/v1/invoices${NC}"
    echo -e "  Expenses:   ${YELLOW}${KONG_PROXY_URL}/api/v1/expenses${NC}"
    echo -e "  Products:   ${YELLOW}${KONG_PROXY_URL}/api/v1/products${NC}"
    echo -e "  Customers:  ${YELLOW}${KONG_PROXY_URL}/api/v1/customers${NC}"
    echo -e "  Settings:   ${YELLOW}${KONG_PROXY_URL}/api/v1/settings${NC}"
    echo -e "  Transport:  ${YELLOW}${KONG_PROXY_URL}/api/v1/transport${NC}"
    echo ""
    echo -e "${CYAN}Management Tools:${NC}"
    echo -e "  Kong Admin GUI:    ${YELLOW}http://localhost:8002${NC}"
    echo -e "  Konga Dashboard:   ${YELLOW}http://localhost:1337${NC}"
    echo ""
    echo -e "${CYAN}Monitoring:${NC}"
    echo -e "  Prometheus:        ${YELLOW}http://localhost:9090${NC}"
    echo -e "  Grafana:           ${YELLOW}http://localhost:3000 (admin/admin_password)${NC}"
    echo -e "  Kibana:            ${YELLOW}http://localhost:5601${NC}"
    echo -e "  Jaeger:            ${YELLOW}http://localhost:16686${NC}"
    echo ""
    echo -e "${GREEN}════════════════════════════════════════════════════════${NC}\n"
}

###############################################################################
# DASHBOARD - Open dashboard URLs
###############################################################################
open_dashboard() {
    log_info "Opening Kong dashboards..."

    local urls=(
        "http://localhost:8002"     # Kong Admin GUI
        "http://localhost:1337"     # Konga Dashboard
        "http://localhost:3000"     # Grafana
        "http://localhost:9090"     # Prometheus
    )

    for url in "${urls[@]}"; do
        if command -v xdg-open &> /dev/null; then
            xdg-open "$url" &
        elif command -v open &> /dev/null; then
            open "$url" &
        else
            log_info "Open: $url"
        fi
    done
}

###############################################################################
# MAIN ROUTING
###############################################################################
main() {
    local command=${1:-help}

    case "$command" in
        start)
            start_services
            ;;
        stop)
            stop_services
            ;;
        restart)
            restart_services
            ;;
        logs)
            view_logs "$2"
            ;;
        status)
            check_status
            ;;
        health)
            health_checks
            ;;
        load-config)
            load_config
            ;;
        reset)
            reset_kong
            ;;
        scale)
            scale_service "$2" "$3"
            ;;
        info)
            show_info
            ;;
        dashboard)
            open_dashboard
            ;;
        help)
            show_help
            ;;
        *)
            log_error "Unknown command: $command"
            show_help
            exit 1
            ;;
    esac
}

# Run main
main "$@"
