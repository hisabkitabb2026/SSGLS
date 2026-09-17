# Load Testing Suite

Comprehensive load testing and chaos engineering suite for InvoiceShelf, built with k6.

## Quick Start

### 1. Install k6

```bash
# macOS
brew install k6

# Ubuntu/Debian
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6-stable.list
sudo apt-get update
sudo apt-get install k6

# Docker
docker pull grafana/k6
```

### 2. Run Tests

```bash
# Run baseline test
./tests/load/run-load-tests.sh baseline

# Run ramp test (0-1000 users over 10 minutes)
./tests/load/run-load-tests.sh ramp

# Run all tests sequentially
./tests/load/run-load-tests.sh all

# With custom API endpoint
BASE_URL=http://localhost:8000/api/v1 ./tests/load/run-load-tests.sh baseline
```

## Test Files

| File | Duration | Users | Purpose |
|------|----------|-------|---------|
| `baseline.k6.js` | 9 min | 10 | Establish performance baseline |
| `ramp.k6.js` | 10 min | 0-1000 | Gradual load increase |
| `stress.k6.js` | 14 min | 100-5000 | Find breaking point |
| `spike.k6.js` | 7 min | 100-2000 spikes | Test recovery from spikes |
| `chaos.k6.js` | 14 min | 50 | Inject failure scenarios |

## Core Scripts

### `run-load-tests.sh` - Test Runner
Main entry point for running all load tests.

**Usage:**
```bash
./tests/load/run-load-tests.sh [test_type]

# Test types: baseline, ramp, stress, spike, chaos, all
```

**Features:**
- Parallel test execution
- Automatic result collection
- Health checks before testing
- Summary reporting
- Custom environment variables

**Examples:**
```bash
# Run baseline test
./tests/load/run-load-tests.sh baseline

# Run with custom environment
BASE_URL=http://prod.api.com/v1 \
TEST_EMAIL=loadtest@company.com \
./tests/load/run-load-tests.sh ramp

# Run all tests with output to file
./tests/load/run-load-tests.sh all 2>&1 | tee test-run.log
```

### `chaos-injection.sh` - Chaos Scenarios
Inject various failure conditions for resilience testing.

**Usage:**
```bash
./tests/load/chaos-injection.sh [command] [scenario] [options]

# Commands: inject, recover, list, help
# Scenarios: network-latency, db-pool, service-kill, queue-backlog, redis-crash, disk-pressure, memory-pressure, cpu-saturation
```

**Examples:**
```bash
# List all chaos scenarios
./tests/load/chaos-injection.sh list

# Inject 3-second network latency
sudo ./tests/load/chaos-injection.sh inject network-latency 3000

# Recover from latency
sudo ./tests/load/chaos-injection.sh recover network-latency

# Inject database pool exhaustion
./tests/load/chaos-injection.sh inject db-pool

# Run chaos test with latency injection (in separate terminals)
Terminal 1: sudo ./tests/load/chaos-injection.sh inject network-latency
Terminal 2: ./tests/load/run-load-tests.sh chaos
Terminal 1: sudo ./tests/load/chaos-injection.sh recover network-latency
```

## k6 Scripts

### Baseline Test (`baseline.k6.js`)

Establishes performance baseline with 10 concurrent users over 9 minutes.

**Measures:**
- Response times (p50, p75, p95, p99)
- Success/error rates
- Per-endpoint performance
- Database query times
- Authentication latency

**Stages:**
- 2 min ramp-up (0-10 users)
- 5 min steady state
- 2 min ramp-down

**Run:**
```bash
k6 run tests/load/baseline.k6.js
```

### Ramp Test (`ramp.k6.js`)

Gradually scales load from 0 to 1000 concurrent users over 10 minutes.

**Key Metrics:**
- Throughput under increasing load
- Scalability limits
- Bottleneck identification
- Graceful degradation

**Stages:**
- 1 min: 0-100 users
- 2 min: 100-500 users
- 2 min: 500-1000 users
- 3 min: sustained 1000 users
- 2 min: ramp-down

**Thresholds:**
- p95 < 2000ms
- p99 < 5000ms
- Error rate < 20%

**Run:**
```bash
k6 run tests/load/ramp.k6.js --vus=1000
```

### Stress Test (`stress.k6.js`)

Continuously increases load until system breaks (14 minutes).

**Determines:**
- Maximum capacity
- Breaking point
- Resource exhaustion patterns
- Graceful failure behavior

**Load progression:**
- 100 → 200 → 500 → 1000 → 2000 → 5000 users
- 2 minutes per stage

**Run:**
```bash
k6 run tests/load/stress.k6.js
```

### Spike Test (`spike.k6.js`)

Simulates sudden traffic spikes and measures recovery (7 minutes).

**Scenarios:**
- Baseline 100 users
- 10-second spike to 500 users → 1 min recovery
- 10-second spike to 1000 users → 1 min recovery
- 10-second spike to 2000 users → 1 min recovery

**Metrics:**
- Time to degrade
- Time to recover
- Failed requests during spike
- Resource spillover

**Run:**
```bash
k6 run tests/load/spike.k6.js
```

### Chaos Test (`chaos.k6.js`)

Tests system resilience under failure conditions (14 minutes).

**Scenarios:**
1. **Baseline** (3 min) - Normal operation
2. **Latency Injection** (3 min) - 5+ second responses
3. **DB Pool Exhaustion** (3 min) - Connection limits hit
4. **Queue Backlog** (3 min) - Consumer unavailable
5. **Intermittent Failures** (3 min) - 30% request failure rate

**Metrics:**
- Recovery rate
- Failure detection time
- Recovery time from failures
- Error rate during failures

**Run:**
```bash
k6 run tests/load/chaos.k6.js
```

**With Chaos Injection:**
```bash
# Terminal 1: Inject network latency
sudo ./tests/load/chaos-injection.sh inject network-latency

# Terminal 2: Run chaos test
./tests/load/run-load-tests.sh chaos

# Terminal 1: Recover
sudo ./tests/load/chaos-injection.sh recover network-latency
```

## Configuration Files

### `baseline-metrics.json`

Defines performance baselines, SLOs, and thresholds.

**Contains:**
- System requirements
- Performance targets
- Per-endpoint baselines
- Database performance targets
- Alerting thresholds
- Capacity planning recommendations

**Key Metrics:**
```json
{
  "response_times": {
    "p50_ms": 100,
    "p95_ms": 500,
    "p99_ms": 1000
  },
  "error_rates": {
    "acceptable": 0.1,
    "warning": 0.5,
    "critical": 5.0
  }
}
```

### `LOAD_TESTING.md`

Comprehensive documentation covering:
- Installation instructions
- Detailed test descriptions
- Performance baselines
- Results analysis
- Troubleshooting guide

## Environment Variables

Customize test behavior with environment variables:

```bash
BASE_URL              # API base URL (default: http://invoiceshelf.test/api/v1)
TEST_EMAIL            # Test account email (default: test@invoiceshelf.test)
TEST_PASSWORD         # Test account password (default: password)
COMPANY_ID            # Company ID for testing (default: 1)
K6_CLOUD_TOKEN        # k6 Cloud token for cloud execution
```

**Examples:**
```bash
# Custom API endpoint
BASE_URL=http://localhost:8000/api/v1 ./tests/load/run-load-tests.sh baseline

# Production-like test
BASE_URL=https://api.company.com/api/v1 \
TEST_EMAIL=loadtest@company.com \
TEST_PASSWORD=$(aws secretsmanager get-secret-value --secret-id LoadTestPassword --query SecretString --output text) \
./tests/load/run-load-tests.sh ramp
```

## Results and Reports

### Output Files

Tests generate results in `tests/load/results/`:

```
results/
├── baseline_20260830_143022.json
├── ramp_20260830_143022.json
├── stress_20260830_143022.json
├── spike_20260830_143022.json
├── chaos_20260830_143022.json
└── REPORT_20260830_143022.md
```

### Analyzing Results

#### With k6 Cloud
```bash
k6 run tests/load/ramp.k6.js --cloud
```

#### Local JSON Analysis
```bash
# Pretty print results
cat results/baseline_*.json | jq '.metrics'

# Extract specific metrics
jq '.metrics.http_req_duration.values' results/baseline_*.json

# Check error rate
jq '.metrics.http_req_failed.values.rate' results/ramp_*.json
```

#### CSV Export
```bash
# Convert JSON to CSV (requires jq and awk)
jq -r '.metrics | to_entries | .[] | [.key, .value.values] | @csv' results/baseline_*.json
```

### Interpreting Metrics

**Response Times:**
- P50: Median response time - baseline should be <100ms
- P95: 95th percentile - watch for spike during ramp
- P99: 99th percentile - tail latency, critical for user experience

**Error Rate:**
- <0.1%: Excellent
- 0.1-0.5%: Good
- 0.5-5%: Warning
- >5%: Critical

**Throughput:**
- Should increase linearly during ramp
- Plateau indicates bottleneck
- Decline indicates breaking point

## Performance Optimization

### Based on Test Results

**If Response Times Increase:**
1. Check database query performance
2. Monitor cache hit rates
3. Profile slow endpoints
4. Optimize N+1 queries

**If Error Rate Increases:**
1. Check database connections
2. Monitor Redis memory
3. Review queue processing
4. Check application logs

**If Throughput Plateaus:**
1. Identify limiting resource (CPU, memory, DB, I/O)
2. Optimize that resource
3. Add horizontal scaling
4. Implement caching

## Integration with CI/CD

### GitHub Actions

```yaml
name: Load Testing
on:
  schedule:
    - cron: '0 2 * * 0'  # Weekly

jobs:
  load-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - uses: grafana/k6-action@v0.3.0
        with:
          filename: tests/load/baseline.k6.js
          cloud: true
        env:
          K6_CLOUD_TOKEN: ${{ secrets.K6_CLOUD_TOKEN }}
```

### GitLab CI

```yaml
load_test:
  stage: test
  image: grafana/k6:latest
  script:
    - k6 run tests/load/baseline.k6.js
  artifacts:
    paths:
      - results/
```

## Best Practices

1. **Run tests regularly** - Weekly baseline, monthly full suite
2. **Test in isolation** - Use dedicated test environment
3. **Monitor infrastructure** - Watch CPU, memory, disk, network
4. **Capture full logs** - Application and system logs during tests
5. **Document baselines** - Track changes over time
6. **Test recovery** - Verify system recovers from failures
7. **Incremental load** - Don't jump to max capacity
8. **Replicate production** - Similar hardware, config, data volume

## Troubleshooting

### k6 Not Found
```bash
# Install k6
brew install k6  # macOS
sudo apt-get install k6  # Ubuntu

# Or use Docker
docker run -v $(pwd):/scripts grafana/k6:latest run /scripts/tests/load/baseline.k6.js
```

### Authentication Fails
```bash
# Verify test credentials exist
curl -X POST http://invoiceshelf.test/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@invoiceshelf.test","password":"password"}'
```

### High Error Rates
```bash
# Check API health
curl http://invoiceshelf.test/api/v1/health

# Check logs
./devenv logs

# Verify database
php artisan tinker
>>> DB::connection()->getPdo()->getAttribute(PDO::ATTR_CONNECTION_STATUS)
```

### Slow Response Times
```bash
# Check slow queries
php artisan tinker
>>> DB::listen(function($query) { Log::info($query); });

# Monitor Redis
redis-cli INFO stats

# Check queue status
php artisan queue:failed
```

## References

- [k6 Documentation](https://k6.io/docs)
- [k6 JavaScript API](https://k6.io/docs/javascript-api)
- [k6 Best Practices](https://k6.io/blog)
- [Performance Testing Handbook](https://launchdarkly.com/blog/load-testing)
- [Chaos Engineering](https://principlesofchaos.org/)

## Support

For issues or questions:
1. Check `LOAD_TESTING.md` for detailed documentation
2. Review k6 documentation: https://k6.io/docs
3. Check test output and logs
4. Run health checks and diagnostics

## License

Same as InvoiceShelf (MIT)
