#!/bin/bash

###############################################################################
# InvoiceShelf Monitoring Stack Setup Script
# Initializes ELK, Prometheus, Grafana, and Jaeger
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if Docker is running
check_docker() {
    print_status "Checking Docker installation..."
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed"
        exit 1
    fi

    if ! docker ps &> /dev/null; then
        print_error "Docker daemon is not running"
        exit 1
    fi

    print_success "Docker is running"
}

# Check if Docker Compose is available
check_docker_compose() {
    print_status "Checking Docker Compose..."
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed"
        exit 1
    fi

    print_success "Docker Compose is available"
}

# Create necessary directories
create_directories() {
    print_status "Creating directory structure..."

    mkdir -p infrastructure/observability/grafana-provisioning/datasources
    mkdir -p infrastructure/observability/grafana-provisioning/dashboards
    mkdir -p infrastructure/observability/grafana-provisioning/notifiers
    mkdir -p infrastructure/observability/dashboards
    mkdir -p logs/fluent-bit

    print_success "Directories created"
}

# Start monitoring services
start_monitoring() {
    print_status "Starting monitoring stack..."

    docker-compose -f docker-compose.microservices.yml \
                   -f docker-compose.monitoring.yml up -d

    if [ $? -eq 0 ]; then
        print_success "Monitoring stack started"
    else
        print_error "Failed to start monitoring stack"
        exit 1
    fi
}

# Wait for services to be healthy
wait_for_services() {
    print_status "Waiting for services to be ready..."

    services=(
        "elasticsearch:9200"
        "logstash:9600"
        "kibana:5601"
        "prometheus:9090"
        "grafana:3000"
        "alertmanager:9093"
        "jaeger:16686"
    )

    for service in "${services[@]}"; do
        host="${service%:*}"
        port="${service##*:}"
        max_attempts=30
        attempt=0

        print_status "Waiting for $host:$port..."

        while [ $attempt -lt $max_attempts ]; do
            if nc -z localhost "$port" 2>/dev/null; then
                print_success "$host is ready"
                break
            fi

            attempt=$((attempt + 1))
            sleep 2
        done

        if [ $attempt -eq $max_attempts ]; then
            print_warning "$host:$port is not responding (may still be initializing)"
        fi
    done
}

# Initialize Elasticsearch indices and templates
initialize_elasticsearch() {
    print_status "Initializing Elasticsearch..."

    # Wait for Elasticsearch to be ready
    sleep 5

    # Create index template
    curl -X PUT "localhost:9200/_index_template/logs" \
        -H 'Content-Type: application/json' \
        -d @infrastructure/observability/elasticsearch-templates.json \
        2>/dev/null || print_warning "Could not create index template (Elasticsearch may not be ready)"

    print_success "Elasticsearch initialized"
}

# Initialize Prometheus
initialize_prometheus() {
    print_status "Initializing Prometheus..."

    # The config is automatically loaded on startup
    print_success "Prometheus configuration loaded"
}

# Initialize AlertManager
initialize_alertmanager() {
    print_status "Initializing AlertManager..."

    # The config is automatically loaded on startup
    print_success "AlertManager configuration loaded"
}

# Initialize Grafana datasources and dashboards
initialize_grafana() {
    print_status "Initializing Grafana..."

    # Wait for Grafana to be ready
    sleep 10

    # Verify provisioning
    if docker exec invoiceshelf-grafana ls /etc/grafana/provisioning/datasources/ &>/dev/null; then
        print_success "Grafana datasources provisioned"
    else
        print_warning "Grafana datasources not fully provisioned yet"
    fi
}

# Display access information
display_access_info() {
    echo ""
    echo -e "${GREEN}================================${NC}"
    echo -e "${GREEN}Monitoring Stack Ready!${NC}"
    echo -e "${GREEN}================================${NC}"
    echo ""
    echo "Access the following interfaces:"
    echo ""
    echo -e "${BLUE}Grafana (Dashboards)${NC}"
    echo "  URL: http://localhost:3000"
    echo "  User: admin"
    echo "  Password: admin"
    echo ""
    echo -e "${BLUE}Kibana (Log Search)${NC}"
    echo "  URL: http://localhost:5601"
    echo ""
    echo -e "${BLUE}Prometheus (Metrics)${NC}"
    echo "  URL: http://localhost:9090"
    echo ""
    echo -e "${BLUE}AlertManager (Alerts)${NC}"
    echo "  URL: http://localhost:9093"
    echo ""
    echo -e "${BLUE}Jaeger (Tracing)${NC}"
    echo "  URL: http://localhost:16686"
    echo ""
    echo -e "${BLUE}cAdvisor (Container Metrics)${NC}"
    echo "  URL: http://localhost:8080"
    echo ""
    echo "================================"
    echo ""
}

# Verify all components
verify_components() {
    print_status "Verifying all components..."

    components_ok=true

    # Check Elasticsearch
    if curl -s http://localhost:9200 > /dev/null; then
        print_success "Elasticsearch is running"
    else
        print_warning "Elasticsearch is not responding"
        components_ok=false
    fi

    # Check Logstash
    if curl -s http://localhost:9600 > /dev/null; then
        print_success "Logstash is running"
    else
        print_warning "Logstash is not responding"
        components_ok=false
    fi

    # Check Kibana
    if curl -s http://localhost:5601 > /dev/null; then
        print_success "Kibana is running"
    else
        print_warning "Kibana is not responding"
        components_ok=false
    fi

    # Check Prometheus
    if curl -s http://localhost:9090/-/healthy > /dev/null; then
        print_success "Prometheus is running"
    else
        print_warning "Prometheus is not responding"
        components_ok=false
    fi

    # Check Grafana
    if curl -s http://localhost:3000 > /dev/null; then
        print_success "Grafana is running"
    else
        print_warning "Grafana is not responding"
        components_ok=false
    fi

    # Check AlertManager
    if curl -s http://localhost:9093/-/healthy > /dev/null; then
        print_success "AlertManager is running"
    else
        print_warning "AlertManager is not responding"
        components_ok=false
    fi

    # Check Jaeger
    if curl -s http://localhost:16686/api/traces > /dev/null; then
        print_success "Jaeger is running"
    else
        print_warning "Jaeger is not responding"
        components_ok=false
    fi

    if [ "$components_ok" = true ]; then
        print_success "All components verified"
    else
        print_warning "Some components are not ready yet - they may still be initializing"
    fi
}

# Main execution
main() {
    echo -e "${BLUE}"
    echo "╔════════════════════════════════════════════════╗"
    echo "║   InvoiceShelf Monitoring Stack Setup          ║"
    echo "║   ELK + Prometheus + Grafana + Jaeger          ║"
    echo "╚════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""

    check_docker
    check_docker_compose
    create_directories
    start_monitoring
    wait_for_services

    # Small delay to ensure services are fully initialized
    sleep 10

    initialize_elasticsearch
    initialize_prometheus
    initialize_alertmanager
    initialize_grafana

    sleep 5

    verify_components
    display_access_info

    print_success "Setup completed successfully!"
}

# Run main function
main
