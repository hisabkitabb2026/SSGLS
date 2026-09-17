#!/bin/bash

###############################################################################
# End-to-End Test Orchestration Script
#
# Usage:
#   ./run_e2e_tests.sh                    # Run all tests
#   ./run_e2e_tests.sh --journey          # Run journey tests only
#   ./run_e2e_tests.sh --auth             # Run auth tests only
#   ./run_e2e_tests.sh --gateway          # Run gateway tests only
#   ./run_e2e_tests.sh --events           # Run event tests only
#   ./run_e2e_tests.sh --consistency      # Run consistency tests only
#   ./run_e2e_tests.sh --parallel         # Run tests in parallel
#   ./run_e2e_tests.sh --report           # Generate HTML report
#   ./run_e2e_tests.sh --cleanup          # Cleanup Docker services
###############################################################################

set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Configuration
COMPOSE_FILE="${PROJECT_ROOT}/docker-compose.tests.yml"
PYTEST_CONFIG="${SCRIPT_DIR}/pytest_e2e.ini"
TESTS_DIR="${SCRIPT_DIR}"
REPORT_DIR="${SCRIPT_DIR}/reports"
LOG_FILE="${SCRIPT_DIR}/e2e_tests.log"

# Defaults
TEST_FILTER=""
PARALLEL=false
GENERATE_REPORT=false
CLEANUP_ONLY=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --journey)
            TEST_FILTER="-m journey"
            shift
            ;;
        --auth)
            TEST_FILTER="-m auth"
            shift
            ;;
        --gateway)
            TEST_FILTER="-m gateway"
            shift
            ;;
        --events)
            TEST_FILTER="-m events"
            shift
            ;;
        --consistency)
            TEST_FILTER="-m consistency"
            shift
            ;;
        --parallel)
            PARALLEL=true
            shift
            ;;
        --report)
            GENERATE_REPORT=true
            shift
            ;;
        --cleanup)
            CLEANUP_ONLY=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

# Check prerequisites
check_prerequisites() {
    print_header "Checking Prerequisites"

    # Check Docker
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed"
        exit 1
    fi
    log_success "Docker is installed"

    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed"
        exit 1
    fi
    log_success "Docker Compose is installed"

    # Check Python
    if ! command -v python3 &> /dev/null; then
        log_error "Python 3 is not installed"
        exit 1
    fi
    log_success "Python 3 is installed"

    # Check Pytest
    if ! python3 -m pip show pytest &> /dev/null; then
        log_warning "Pytest is not installed. Installing..."
        python3 -m pip install -q -r "${SCRIPT_DIR}/requirements_e2e.txt"
    fi
    log_success "Pytest is installed"
}

# Start services
start_services() {
    print_header "Starting Docker Services"

    if docker-compose -f "$COMPOSE_FILE" ps | grep -q "Up"; then
        log_warning "Services are already running"
        return
    fi

    log_info "Bringing up services..."
    docker-compose -f "$COMPOSE_FILE" up -d

    log_info "Waiting for services to be healthy..."
    local max_attempts=60
    local attempt=0

    while [ $attempt -lt $max_attempts ]; do
        if docker-compose -f "$COMPOSE_FILE" ps | grep -E "(invoice|expense|product|customer|settings|transport|kong)" | grep -q "Up"; then
            log_success "Services are running"

            # Give services a moment to fully initialize
            sleep 5
            return
        fi

        attempt=$((attempt + 1))
        sleep 1
    done

    log_error "Services failed to start within timeout"
    docker-compose -f "$COMPOSE_FILE" logs
    exit 1
}

# Stop services
stop_services() {
    print_header "Stopping Docker Services"

    if docker-compose -f "$COMPOSE_FILE" ps | grep -q "Up"; then
        log_info "Stopping services..."
        docker-compose -f "$COMPOSE_FILE" down -v
        log_success "Services stopped"
    fi
}

# Run tests
run_tests() {
    print_header "Running E2E Tests"

    mkdir -p "$REPORT_DIR"

    local pytest_cmd="python3 -m pytest"
    local pytest_args="--config-file=$PYTEST_CONFIG -v --tb=short"

    if [ -n "$TEST_FILTER" ]; then
        pytest_args="$pytest_args $TEST_FILTER"
    fi

    if [ "$PARALLEL" = true ]; then
        pytest_args="$pytest_args -n auto"
        log_info "Running tests in parallel"
    fi

    if [ "$GENERATE_REPORT" = true ]; then
        pytest_args="$pytest_args --html=$REPORT_DIR/report.html --self-contained-html"
        log_info "HTML report will be generated at $REPORT_DIR/report.html"
    fi

    pytest_args="$pytest_args --junit-xml=$REPORT_DIR/results.xml"
    pytest_args="$pytest_args --cov=. --cov-report=html:$REPORT_DIR/coverage --cov-report=xml"

    log_info "Command: $pytest_cmd $pytest_args $TESTS_DIR"
    echo ""

    cd "$TESTS_DIR"

    # Run tests and capture exit code
    if $pytest_cmd $pytest_args "$TESTS_DIR" 2>&1 | tee -a "$LOG_FILE"; then
        return 0
    else
        return 1
    fi
}

# Analyze results
analyze_results() {
    print_header "Test Results Analysis"

    if [ ! -f "$REPORT_DIR/results.xml" ]; then
        log_warning "No test results file found"
        return
    fi

    # Parse XML results (basic parsing)
    local total=$(grep -o 'tests="[0-9]*"' "$REPORT_DIR/results.xml" | grep -o '[0-9]*' | head -1)
    local failures=$(grep -o 'failures="[0-9]*"' "$REPORT_DIR/results.xml" | grep -o '[0-9]*' | head -1)
    local errors=$(grep -o 'errors="[0-9]*"' "$REPORT_DIR/results.xml" | grep -o '[0-9]*' | head -1)
    local skipped=$(grep -o 'skipped="[0-9]*"' "$REPORT_DIR/results.xml" | grep -o '[0-9]*' | head -1)

    log_info "Total Tests: $total"
    log_info "Passed: $((total - failures - errors - skipped))"
    log_info "Failures: $failures"
    log_info "Errors: $errors"
    log_info "Skipped: $skipped"

    if [ "$failures" -eq 0 ] && [ "$errors" -eq 0 ]; then
        log_success "All tests passed!"
        return 0
    else
        log_error "Some tests failed"
        return 1
    fi
}

# Main execution
main() {
    {
        echo "=================================="
        echo "E2E Test Run: $(date)"
        echo "=================================="
    } > "$LOG_FILE"

    log_info "Starting E2E test execution"

    if [ "$CLEANUP_ONLY" = true ]; then
        stop_services
        exit 0
    fi

    # Execute test pipeline
    check_prerequisites
    start_services

    # Run tests
    if run_tests; then
        test_result=0
    else
        test_result=1
    fi

    # Analyze and report
    if [ "$GENERATE_REPORT" = true ] || [ "$test_result" -eq 0 ]; then
        analyze_results
    fi

    # Cleanup
    log_info "Stopping services..."
    stop_services

    print_header "Test Execution Complete"
    log_info "Logs saved to: $LOG_FILE"

    if [ "$GENERATE_REPORT" = true ]; then
        log_info "Report directory: $REPORT_DIR"
        if [ -f "$REPORT_DIR/report.html" ]; then
            log_success "HTML report generated: $REPORT_DIR/report.html"
        fi
    fi

    exit $test_result
}

# Run main
main
