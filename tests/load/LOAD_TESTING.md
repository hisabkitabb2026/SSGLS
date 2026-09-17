# Load Testing Suite for InvoiceShelf

Comprehensive load testing suite using k6 to measure performance, stress resilience, and chaos recovery capabilities.

## Installation

### Install k6

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

## Test Suites

### 1. Baseline Test (`baseline.k6.js`)
Establishes performance baseline with 10 concurrent users.

**Duration:** 9 minutes
- 2 min ramp-up (0-10 users)
- 5 min steady state (10 users)
- 2 min ramp-down (10-0 users)

**Measures:**
- Response times (min, max, avg, p95, p99)
- Success/error rates
- Query execution times
- Authentication performance
- Dashboard load time
- Resource listing performance

**Run:**
```bash
k6 run tests/load/baseline.k6.js \
  --vus=10 \
  --duration=9m \
  -e BASE_URL="http://invoiceshelf.test/api/v1" \
  -e TEST_EMAIL="test@invoiceshelf.test" \
  -e TEST_PASSWORD="password" \
  -e COMPANY_ID="1"
```

### 2. Ramp Test (`ramp.k6.js`)
Gradually scales load from 0 to 1000 concurrent users over 10 minutes.

**Duration:** 10 minutes
- 1 min: 0-100 users
- 2 min: 100-500 users
- 2 min: 500-1000 users
- 3 min: sustained 1000 users
- 2 min: ramp-down

**Key Metrics:**
- Throughput under ramp
- Bottleneck identification
- Scalability limits
- Response time degradation

**Thresholds:**
- p95 response time < 2000ms
- p99 response time < 5000ms
- Error rate < 20%

**Run:**
```bash
k6 run tests/load/ramp.k6.js \
  --vus=1000 \
  --duration=10m \
  -e BASE_URL="http://invoiceshelf.test/api/v1" \
  -e TEST_EMAIL="test@invoiceshelf.test" \
  -e TEST_PASSWORD="password"
```

### 3. Stress Test (`stress.k6.js`)
Continuously increases load until system breaks or becomes unresponsive.

**Duration:** 14 minutes
- Stages: 100 → 200 → 500 → 1000 → 2000 → 5000 users
- 2 minutes per stage
- Final ramp-down

**Determines:**
- Maximum capacity
- Breaking point
- Graceful degradation behavior
- Resource exhaustion patterns

**Thresholds:**
- p99 response time < 10000ms
- Error rate < 50%

**Run:**
```bash
k6 run tests/load/stress.k6.js \
  --vus=5000 \
  --duration=14m \
  -e BASE_URL="http://invoiceshelf.test/api/v1" \
  -e TEST_EMAIL="test@invoiceshelf.test" \
  -e TEST_PASSWORD="password"
```

### 4. Spike Test (`spike.k6.js`)
Simulates sudden traffic spikes and measures recovery.

**Duration:** 7 minutes
- 1 min baseline (100 users)
- 10 sec spike to 500 users → 1 min recovery
- 10 sec spike to 1000 users → 1 min recovery
- 10 sec spike to 2000 users → 1 min recovery

**Measures:**
- Time to degrade
- Time to recover
- Failed requests during spike
- Resource spillover

**Thresholds:**
- p95 response time < 3000ms
- Error rate < 30%

**Run:**
```bash
k6 run tests/load/spike.k6.js \
  --vus=2000 \
  --duration=7m \
  -e BASE_URL="http://invoiceshelf.test/api/v1" \
  -e TEST_EMAIL="test@invoiceshelf.test" \
  -e TEST_PASSWORD="password"
```

### 5. Chaos Test (`chaos.k6.js`)
Tests system resilience under failure conditions.

**Duration:** 14 minutes
**Scenarios (3 min each):**

1. **Baseline** - Normal operation
2. **Latency Injection** - Service slowdowns (5+ second responses)
3. **DB Pool Exhaustion** - Connection limit reached
4. **Message Queue Backlog** - Queue consumer unavailable
5. **Intermittent Failures** - 30% of requests fail

**Chaos Injection Methods (Manual):**

#### Kill a Service
```bash
# Kill PHP-FPM
pkill -9 -f php-fpm

# Kill Queue Listener
pkill -9 -f queue:listen

# Kill Redis
redis-cli SHUTDOWN

# Restart
php artisan queue:listen --tries=1 &
```

#### Inject Latency
```bash
# Add 3-second latency to all outgoing traffic
sudo tc qdisc add dev eth0 root netem delay 3000ms

# Remove latency
sudo tc qdisc delete dev eth0 root
```

#### Exhaust Database Pool
```bash
# Reduce connection pool size
# Edit config/database.php or .env and set DB_POOL_MIN=1, DB_POOL_MAX=2

# Or kill connections on running database
# MySQL
mysql -e "KILL CONNECTION 123;"

# PostgreSQL
SELECT pg_terminate_backend(pid) FROM pg_stat_activity;
```

#### Backup Message Queue
```bash
# Pause queue processing (but keep accepting jobs)
# Edit app/Jobs/ProcessInvoice.php to introduce delay:
sleep(5);

# Or manually fill queue
php artisan tinker
>>> for ($i = 0; $i < 1000; $i++) {
      Job::dispatch();
    }
```

**Run:**
```bash
k6 run tests/load/chaos.k6.js \
  --vus=50 \
  --duration=14m \
  -e BASE_URL="http://invoiceshelf.test/api/v1" \
  -e TEST_EMAIL="test@invoiceshelf.test" \
  -e TEST_PASSWORD="password"
```

## Performance Baseline Metrics

### Target Performance SLOs

| Metric | Target | Warning | Critical |
|--------|--------|---------|----------|
| Response Time P50 | <100ms | >200ms | >500ms |
| Response Time P95 | <500ms | >1000ms | >2000ms |
| Response Time P99 | <1000ms | >2000ms | >5000ms |
| Error Rate | <0.1% | >0.5% | >5% |
| Throughput | >100 req/s | <50 req/s | <10 req/s |
| DB Query Time | <50ms | >100ms | >500ms |
| Uptime | 99.99% | 99% | <99% |

### Endpoint-Specific Baselines

**Authentication:**
- Response Time P95: <200ms
- Success Rate: 99.99%

**Dashboard:**
- Response Time P95: <1000ms
- Success Rate: 99.9%

**Invoice Listing:**
- Response Time P95: <800ms
- Success Rate: 99.9%

**Invoice Creation:**
- Response Time P95: <2000ms
- Success Rate: 99.8%

**Customer Listing:**
- Response Time P95: <800ms
- Success Rate: 99.9%

## Running Tests with Docker

If using the Docker dev environment:

```bash
# Start the environment
./devenv start

# Open shell in container
./devenv shell

# Run k6 tests
k6 run tests/load/baseline.k6.js

# Or use Docker's k6 image
docker run -i grafana/k6:latest run - \
  -e BASE_URL="http://host.docker.internal/api/v1" \
  < tests/load/baseline.k6.js
```

## Continuous Integration

### GitHub Actions Integration

```yaml
name: Load Testing

on:
  schedule:
    - cron: '0 2 * * 0' # Weekly Sunday 2 AM

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

## Output and Reporting

### JSON Output
```bash
k6 run tests/load/ramp.k6.js --out json=results.json
```

### Summary Report
```bash
k6 run tests/load/ramp.k6.js --summary-export=summary.json
```

### CSV Export (via handler)
The scripts include `handleSummary` to export results.

## Analyzing Results

### Key Metrics to Monitor

1. **Response Times**
   - P95 and P99 percentiles indicate tail latency
   - Should remain stable during ramp and spike tests
   - Alert if exceeds 2-5x baseline

2. **Error Rate**
   - Should stay <0.1% during normal load
   - May rise during stress, but should recover during spike
   - >5% indicates critical issues

3. **Throughput**
   - Requests per second
   - Should increase linearly during ramp
   - Plateau or decrease indicates bottleneck

4. **Resource Utilization**
   - CPU usage
   - Memory usage
   - Database connections
   - Redis connections
   - Network bandwidth

5. **Query Performance**
   - Waiting time (db_query_time)
   - Should remain consistent
   - Sharp increases indicate DB bottleneck

## Troubleshooting

### High Error Rates
- Check database connectivity
- Verify Redis is running
- Check queue listener is running
- Review application logs: `./devenv logs`

### High Response Times
- Monitor database queries: `EXPLAIN ANALYZE`
- Check cache hit rates
- Verify network latency
- Review slow query log

### Memory Issues
- Check for memory leaks in application
- Monitor Redis memory usage
- Review queue job processing
- Check file descriptor limits

### Connection Pool Exhaustion
- Increase pool size in config
- Reduce connection timeout
- Optimize query time
- Add connection pooling service (PgBouncer)

## Best Practices

1. **Run tests in isolated environment** - Don't test production
2. **Baseline first** - Establish normal behavior before stress testing
3. **Monitor infrastructure** - Watch CPU, memory, network during tests
4. **Run multiple times** - Results vary, take averages
5. **Incremental load** - Don't jump to max capacity immediately
6. **Review logs** - Check application and system logs for errors
7. **Replicate production** - Use similar hardware and configuration
8. **Test recovery** - Verify system recovers after spike/failure

## Resources

- [k6 Documentation](https://k6.io/docs)
- [k6 API Reference](https://k6.io/docs/javascript-api)
- [Performance Testing Best Practices](https://k6.io/blog)
- [LaunchDarkly Load Testing Guide](https://launchdarkly.com/blog/load-testing)
