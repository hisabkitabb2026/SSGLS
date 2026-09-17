#!/bin/bash

##############################################################################
# Chaos Injection Helper Script
#
# Injects various failure scenarios for chaos testing
# Usage: ./tests/load/chaos-injection.sh [inject|recover] [scenario]
##############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_header() {
    echo -e "${BLUE}===================================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}===================================================${NC}"
    echo
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Check if running with appropriate permissions
check_privileges() {
    if [ "$EUID" -ne 0 ] && [ "$1" != "service" ]; then
        print_error "Some chaos scenarios require sudo privileges"
        echo "Run with: sudo ./tests/load/chaos-injection.sh $@"
        exit 1
    fi
}

# ========================================
# SCENARIO 1: Service Latency Injection
# ========================================

inject_network_latency() {
    local latency_ms=${1:-3000}

    print_info "Injecting ${latency_ms}ms network latency..."

    if [ "$(uname)" = "Linux" ]; then
        # Using tc (traffic control) on Linux
        if ! command -v tc &> /dev/null; then
            print_error "tc command not found. Install with: apt-get install iproute2"
            return 1
        fi

        # Find primary network interface
        local interface=$(ip route | grep default | awk '{print $5}' | head -1)

        if [ -z "$interface" ]; then
            print_error "Could not determine network interface"
            return 1
        fi

        print_info "Using interface: $interface"

        # Add qdisc (queuing discipline)
        sudo tc qdisc add dev "$interface" root netem delay "${latency_ms}ms"
        print_success "Network latency injected: ${latency_ms}ms on $interface"

    elif [ "$(uname)" = "Darwin" ]; then
        # macOS - using Network Link Conditioner or similar
        print_warning "Latency injection on macOS requires Network Link Conditioner"
        print_info "Download: https://developer.apple.com/download/"
        return 1
    fi
}

recover_network_latency() {
    print_info "Removing network latency injection..."

    if [ "$(uname)" = "Linux" ]; then
        local interface=$(ip route | grep default | awk '{print $5}' | head -1)

        if [ -z "$interface" ]; then
            print_error "Could not determine network interface"
            return 1
        fi

        sudo tc qdisc delete dev "$interface" root netem 2>/dev/null || true
        print_success "Network latency removed"
    fi
}

# ========================================
# SCENARIO 2: Database Connection Pool Exhaustion
# ========================================

inject_db_pool_exhaustion() {
    print_info "Injecting database connection pool exhaustion..."

    # This creates heavy load on DB connections
    # For MySQL
    if command -v mysql &> /dev/null; then
        print_info "Detected MySQL"
        # Show current connections
        mysql -u root -p"${DB_PASSWORD:-}" -e "SHOW STATUS WHERE variable_name LIKE 'Threads%';" 2>/dev/null || true

        # Kill some connections to simulate pool exhaustion
        print_warning "To simulate exhaustion, update config/database.php:"
        echo "  'pool_min' => 1,"
        echo "  'pool_max' => 2,"
        print_info "Or kill connections: mysql -e \"KILL CONNECTION <id>;\""
    fi

    # For PostgreSQL
    if command -v psql &> /dev/null; then
        print_info "Detected PostgreSQL"
        print_warning "To simulate exhaustion, run:"
        echo "  SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE state = 'idle';"
    fi
}

recover_db_pool_exhaustion() {
    print_info "DB pool exhaustion is memory-based and auto-recovers"
    print_success "Restart database service if needed: systemctl restart mysql / postgres"
}

# ========================================
# SCENARIO 3: Kill PHP-FPM Service
# ========================================

inject_service_kill() {
    local service=${1:-php-fpm}

    print_warning "This will kill the $service service!"
    echo -n "Continue? (yes/no): "
    read -r confirm

    if [ "$confirm" != "yes" ]; then
        print_info "Cancelled"
        return 1
    fi

    print_info "Killing $service service..."

    if [ "$(uname)" = "Linux" ]; then
        sudo pkill -9 -f "$service" || true
        print_success "$service killed"
        print_info "The service should auto-restart. Check status with: systemctl status $service"
    else
        pkill -9 -f "$service" || true
        print_success "$service killed"
    fi
}

recover_service_kill() {
    print_info "Waiting for service to auto-restart..."
    sleep 5

    if command -v systemctl &> /dev/null; then
        systemctl start php-fpm || true
        print_success "Service restarted"
    else
        print_warning "Service should auto-restart. Verify manually."
    fi
}

# ========================================
# SCENARIO 4: Kill Queue Listener
# ========================================

inject_queue_backlog() {
    print_info "Stopping queue listener to create backlog..."

    pkill -f "artisan queue:listen" || true
    pkill -f "artisan queue:work" || true

    print_success "Queue listener stopped"
    print_warning "Jobs will accumulate in the queue"
    print_info "Queue jobs remain in Redis and will be processed when listener restarts"
}

recover_queue_backlog() {
    print_info "Restarting queue listener..."

    # If running in foreground, Ctrl+C would stop it
    # For background process:
    php artisan queue:listen --tries=1 > /tmp/queue.log 2>&1 &

    print_success "Queue listener restarted"
    print_info "Check queue status with: php artisan queue:failed"
}

# ========================================
# SCENARIO 5: Redis Cache Crash
# ========================================

inject_redis_crash() {
    print_warning "This will stop Redis!"
    echo -n "Continue? (yes/no): "
    read -r confirm

    if [ "$confirm" != "yes" ]; then
        print_info "Cancelled"
        return 1
    fi

    print_info "Stopping Redis..."

    if command -v systemctl &> /dev/null; then
        sudo systemctl stop redis-server
        print_success "Redis stopped"
    elif command -v redis-cli &> /dev/null; then
        redis-cli SHUTDOWN
        print_success "Redis shutdown"
    else
        print_error "Cannot find Redis to stop"
        return 1
    fi
}

recover_redis_crash() {
    print_info "Starting Redis..."

    if command -v systemctl &> /dev/null; then
        sudo systemctl start redis-server
    else
        redis-server &
    fi

    sleep 2
    print_success "Redis restarted"

    # Verify
    if redis-cli ping > /dev/null 2>&1; then
        print_success "Redis is responsive"
    else
        print_error "Redis failed to start"
    fi
}

# ========================================
# SCENARIO 6: Disk Space (Simulated)
# ========================================

inject_disk_pressure() {
    print_info "Creating large temporary files to simulate disk pressure..."

    local size=${1:-500M}
    local file="/tmp/chaos_disk_fill_${RANDOM}.bin"

    dd if=/dev/zero of="$file" bs=1M count="${size%M}" 2>/dev/null
    print_success "Created ${size} disk pressure file: $file"
    print_warning "Free disk space may be limited"
    echo "To recover, run: rm $file"
}

recover_disk_pressure() {
    print_info "Cleaning up disk pressure files..."
    rm -f /tmp/chaos_disk_fill_*.bin
    print_success "Disk pressure files removed"
}

# ========================================
# SCENARIO 7: Memory Pressure (Simulated)
# ========================================

inject_memory_pressure() {
    print_info "Creating memory pressure simulation..."
    print_warning "This will allocate significant RAM!"

    # Use stress-ng if available
    if command -v stress-ng &> /dev/null; then
        local memory_gb=${1:-4}
        stress-ng --vm 1 --vm-bytes "${memory_gb}G" --timeout 60s --verbose &
        print_success "Memory pressure started (${memory_gb}GB for 60s)"
    else
        print_error "stress-ng not installed. Install with: apt-get install stress-ng"
        return 1
    fi
}

recover_memory_pressure() {
    print_info "Stopping memory pressure..."
    pkill -f stress-ng || true
    print_success "Memory pressure stopped"
}

# ========================================
# SCENARIO 8: CPU Saturation
# ========================================

inject_cpu_saturation() {
    print_info "Creating CPU saturation..."

    if command -v stress-ng &> /dev/null; then
        stress-ng --cpu "$(nproc)" --timeout 60s --verbose &
        print_success "CPU saturation started (100% load for 60s)"
    else
        print_error "stress-ng not installed"
        return 1
    fi
}

recover_cpu_saturation() {
    print_info "Stopping CPU saturation..."
    pkill -f stress-ng || true
    print_success "CPU saturation stopped"
}

# ========================================
# Management Functions
# ========================================

list_scenarios() {
    cat << EOF
Available Chaos Scenarios:

  1. network-latency  - Inject 3 second network delay
  2. db-pool          - Simulate DB connection pool exhaustion
  3. service-kill     - Kill PHP-FPM service
  4. queue-backlog    - Pause queue listener
  5. redis-crash      - Stop Redis service
  6. disk-pressure    - Fill disk space
  7. memory-pressure  - Allocate large amounts of RAM
  8. cpu-saturation   - Saturate CPU cores

Examples:

  # Inject network latency
  sudo ./tests/load/chaos-injection.sh inject network-latency

  # Recover from latency
  sudo ./tests/load/chaos-injection.sh recover network-latency

  # Run chaos test while injecting latency
  # In one terminal:
  sudo ./tests/load/chaos-injection.sh inject network-latency

  # In another terminal:
  ./tests/load/run-load-tests.sh chaos

  # Then recover:
  sudo ./tests/load/chaos-injection.sh recover network-latency

EOF
}

show_usage() {
    cat << EOF
Chaos Injection Helper Script

Usage: ./tests/load/chaos-injection.sh [command] [scenario] [options]

Commands:
  inject    - Inject a chaos scenario
  recover   - Recover from a chaos scenario
  list      - List available scenarios
  help      - Show this help message

Examples:
  ./tests/load/chaos-injection.sh list
  sudo ./tests/load/chaos-injection.sh inject network-latency
  sudo ./tests/load/chaos-injection.sh recover network-latency

For more information, see LOAD_TESTING.md

EOF
}

main() {
    local command=${1:-help}
    local scenario=${2:-}
    local option=${3:-}

    case "$command" in
        inject)
            case "$scenario" in
                network-latency)
                    check_privileges "$command"
                    inject_network_latency "${option:-3000}"
                    ;;
                db-pool)
                    inject_db_pool_exhaustion
                    ;;
                service-kill)
                    check_privileges "$command"
                    inject_service_kill "${option:-php-fpm}"
                    ;;
                queue-backlog)
                    inject_queue_backlog
                    ;;
                redis-crash)
                    check_privileges "$command"
                    inject_redis_crash
                    ;;
                disk-pressure)
                    check_privileges "$command"
                    inject_disk_pressure "${option:-500M}"
                    ;;
                memory-pressure)
                    check_privileges "$command"
                    inject_memory_pressure "${option:-4}"
                    ;;
                cpu-saturation)
                    check_privileges "$command"
                    inject_cpu_saturation
                    ;;
                *)
                    print_error "Unknown scenario: $scenario"
                    list_scenarios
                    exit 1
                    ;;
            esac
            ;;

        recover)
            case "$scenario" in
                network-latency)
                    check_privileges "$command"
                    recover_network_latency
                    ;;
                db-pool)
                    recover_db_pool_exhaustion
                    ;;
                service-kill)
                    check_privileges "$command"
                    recover_service_kill
                    ;;
                queue-backlog)
                    recover_queue_backlog
                    ;;
                redis-crash)
                    check_privileges "$command"
                    recover_redis_crash
                    ;;
                disk-pressure)
                    check_privileges "$command"
                    recover_disk_pressure
                    ;;
                memory-pressure)
                    recover_memory_pressure
                    ;;
                cpu-saturation)
                    recover_cpu_saturation
                    ;;
                *)
                    print_error "Unknown scenario: $scenario"
                    exit 1
                    ;;
            esac
            ;;

        list)
            list_scenarios
            ;;

        help|--help|-h)
            show_usage
            ;;

        *)
            print_error "Unknown command: $command"
            show_usage
            exit 1
            ;;
    esac
}

main "$@"
