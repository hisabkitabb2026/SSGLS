#!/bin/bash

##############################################################################
# Load Testing Suite Runner
#
# Runs all load tests and collects results
# Usage: ./tests/load/run-load-tests.sh [baseline|ramp|stress|spike|chaos|all]
##############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="${BASE_URL:-http://invoiceshelf.test/api/v1}"
TEST_EMAIL="${TEST_EMAIL:-test@invoiceshelf.test}"
TEST_PASSWORD="${TEST_PASSWORD:-password}"
COMPANY_ID="${COMPANY_ID:-1}"
RESULTS_DIR="tests/load/results"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Ensure results directory exists
mkdir -p "$RESULTS_DIR"

# Functions
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

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

check_k6() {
    if ! command -v k6 &> /dev/null; then
        print_error "k6 is not installed!"
        echo "Install k6 with:"
        echo "  macOS: brew install k6"
        echo "  Ubuntu: https://k6.io/docs/getting-started/installation"
        echo "  Docker: docker pull grafana/k6"
        exit 1
    fi
    print_success "k6 is installed"
}

check_api_health() {
    print_info "Checking API health..."

    if response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/health"); then
        if [ "$response" == "200" ]; then
            print_success "API is healthy (HTTP $response)"
            return 0
        else
            print_error "API returned HTTP $response"
            return 1
        fi
    else
        print_error "Cannot reach API at $BASE_URL"
        return 1
    fi
}

run_test() {
    local test_name=$1
    local test_file=$2
    local description=$3

    print_header "$test_name"
    echo "Description: $description"
    echo "Base URL: $BASE_URL"
    echo

    local output_file="$RESULTS_DIR/${test_name}_${TIMESTAMP}.json"
    local summary_file="$RESULTS_DIR/${test_name}_${TIMESTAMP}_summary.json"

    print_info "Running test, results will be saved to: $output_file"

    if k6 run \
        "$test_file" \
        --out "json=$output_file" \
        -e "BASE_URL=$BASE_URL" \
        -e "TEST_EMAIL=$TEST_EMAIL" \
        -e "TEST_PASSWORD=$TEST_PASSWORD" \
        -e "COMPANY_ID=$COMPANY_ID"; then

        print_success "$test_name completed successfully!"
        echo "Results saved to: $output_file"
        echo
        return 0
    else
        print_error "$test_name failed!"
        echo
        return 1
    fi
}

generate_summary_report() {
    local report_file="$RESULTS_DIR/REPORT_${TIMESTAMP}.md"

    print_header "Generating Summary Report"

    cat > "$report_file" << EOF
# Load Testing Report
Generated: $(date)

## Configuration
- Base URL: $BASE_URL
- Test Email: $TEST_EMAIL
- Company ID: $COMPANY_ID

## Results

### Test Files
EOF

    for file in "$RESULTS_DIR"/*.json; do
        if [ -f "$file" ]; then
            echo "- $(basename "$file")" >> "$report_file"
        fi
    done

    cat >> "$report_file" << EOF

## Metrics Summary

### Response Times

| Percentile | Target | Status |
|-----------|--------|--------|
| P50 | <100ms | TBD |
| P95 | <500ms | TBD |
| P99 | <1000ms | TBD |

### Error Rates

| Test | Error Rate | Status |
|------|-----------|--------|
| Baseline | <0.1% | TBD |
| Ramp | <20% | TBD |
| Stress | <50% | TBD |
| Spike | <30% | TBD |

## Recommendations

- Monitor response time trends
- Identify performance bottlenecks
- Plan capacity upgrades if needed
- Review error logs for issues

## Next Steps

1. Analyze detailed results in JSON files
2. Create alerts based on thresholds
3. Schedule weekly baseline runs
4. Perform root cause analysis on failures

---

For detailed analysis, see the individual JSON result files.
EOF

    print_success "Report generated: $report_file"
}

analyze_results() {
    print_header "Analyzing Results"

    if [ ! -f "$1" ]; then
        print_error "Results file not found: $1"
        return 1
    fi

    # Extract key metrics from JSON
    echo "Metrics from: $(basename "$1")"
    echo

    # Using jq if available, otherwise just indicate where to find data
    if command -v jq &> /dev/null; then
        # Try to extract metrics
        if jq '.metrics' "$1" > /dev/null 2>&1; then
            echo "Request Count:"
            jq '.metrics.http_reqs.values.value // "N/A"' "$1" 2>/dev/null | xargs echo "  Total:"

            echo
            echo "Duration:"
            jq '.duration' "$1" 2>/dev/null | xargs echo "  Total:"
        fi
    else
        print_info "jq not installed. View results with: cat $1"
    fi
}

show_usage() {
    cat << EOF
Usage: $0 [baseline|ramp|stress|spike|chaos|all]

Test Descriptions:

  baseline  - Establish performance baseline with 10 concurrent users (9 min)
  ramp      - Gradually scale from 0 to 1000 users over 10 minutes
  stress    - Continuously increase load until breaking point (14 min)
  spike     - Simulate sudden traffic spikes and measure recovery (7 min)
  chaos     - Test resilience under failure conditions (14 min)
  all       - Run all tests sequentially

Environment Variables:

  BASE_URL       API base URL (default: http://invoiceshelf.test/api/v1)
  TEST_EMAIL     Test account email (default: test@invoiceshelf.test)
  TEST_PASSWORD  Test account password (default: password)
  COMPANY_ID     Company ID for testing (default: 1)

Examples:

  # Run baseline test
  ./tests/load/run-load-tests.sh baseline

  # Run all tests
  ./tests/load/run-load-tests.sh all

  # Run with custom base URL
  BASE_URL=http://localhost:8000/api/v1 ./tests/load/run-load-tests.sh ramp

  # Run spike test with production-like settings
  BASE_URL=http://api.production.com/api/v1 \
  TEST_EMAIL=loadtest@company.com \
  ./tests/load/run-load-tests.sh spike

EOF
}

main() {
    local test_type="${1:-baseline}"

    # Check prerequisites
    check_k6

    print_header "Load Testing Suite for InvoiceShelf"
    echo "API Base URL: $BASE_URL"
    echo "Results Directory: $RESULTS_DIR"
    echo

    # Attempt to check API health (non-blocking)
    if ! check_api_health; then
        print_info "API may be unavailable, but proceeding anyway..."
        echo
    fi

    # Track results
    local failed_tests=0
    local passed_tests=0

    case "$test_type" in
        baseline)
            if run_test "baseline" "tests/load/baseline.k6.js" "Performance baseline with 10 concurrent users"; then
                ((passed_tests++))
            else
                ((failed_tests++))
            fi
            ;;

        ramp)
            if run_test "ramp" "tests/load/ramp.k6.js" "Ramp load from 0 to 1000 users over 10 minutes"; then
                ((passed_tests++))
            else
                ((failed_tests++))
            fi
            ;;

        stress)
            if run_test "stress" "tests/load/stress.k6.js" "Stress test until breaking point"; then
                ((passed_tests++))
            else
                ((failed_tests++))
            fi
            ;;

        spike)
            if run_test "spike" "tests/load/spike.k6.js" "Spike test with sudden load increases"; then
                ((passed_tests++))
            else
                ((failed_tests++))
            fi
            ;;

        chaos)
            if run_test "chaos" "tests/load/chaos.k6.js" "Chaos test with failure injection"; then
                ((passed_tests++))
            else
                ((failed_tests++))
            fi
            ;;

        all)
            print_header "Running All Tests Sequentially"
            echo "This will take approximately 54 minutes"
            echo "Press Ctrl+C to cancel"
            echo

            for test in baseline ramp stress spike chaos; do
                if run_test "$test" "tests/load/$test.k6.js" ""; then
                    ((passed_tests++))
                else
                    ((failed_tests++))
                fi

                # Cool-down period between tests
                if [ "$test" != "chaos" ]; then
                    print_info "Cooling down for 30 seconds before next test..."
                    sleep 30
                fi
            done
            ;;

        help|--help|-h)
            show_usage
            exit 0
            ;;

        *)
            print_error "Unknown test type: $test_type"
            show_usage
            exit 1
            ;;
    esac

    # Generate report
    if [ "$test_type" == "all" ] || [ "$test_type" == "baseline" ]; then
        generate_summary_report
    fi

    # Final summary
    print_header "Test Summary"
    echo "Passed: $passed_tests"
    echo "Failed: $failed_tests"
    echo "Results Directory: $RESULTS_DIR"
    echo

    if [ $failed_tests -gt 0 ]; then
        print_error "Some tests failed!"
        exit 1
    else
        print_success "All tests passed!"
        exit 0
    fi
}

# Run main function
main "$@"
