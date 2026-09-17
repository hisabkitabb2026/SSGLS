# Load Test Results Analysis Guide

Complete guide to interpreting load test results and taking action based on findings.

## Overview

This guide helps you:
1. Understand what metrics mean
2. Identify performance bottlenecks
3. Make optimization decisions
4. Plan capacity upgrades
5. Monitor trends over time

## Key Metrics Explained

### Response Times

**What it measures:** How long the server takes to respond to a request.

**Key percentiles:**
- **P50 (Median)** - 50% of requests respond faster than this
  - Target: <100ms
  - Indicates typical user experience

- **P95** - 95% of requests are faster than this
  - Target: <500ms
  - Most users stay happy here

- **P99** - 99% of requests are faster than this
  - Target: <1000ms
  - Tail latency, affects worst-case users

**What to watch:**
- Response times increase under load → Bottleneck exists
- P99 spikes during ramp → Server struggling under peak
- Response times don't decrease after load drops → Memory leak possible

**Example Analysis:**

```
Baseline (10 users):
  P50: 45ms ✓
  P95: 120ms ✓
  P99: 250ms ✓

Ramp test (1000 users):
  P50: 150ms → 3.3x increase ⚠
  P95: 800ms → 6.7x increase ⚠
  P99: 2500ms → 10x increase ❌

Action: Investigate database performance, implement caching
```

### Error Rate

**What it measures:** Percentage of requests that fail (5xx responses, timeouts, connection errors).

**Acceptable ranges:**
- **<0.1%** - Excellent, business as usual
- **0.1-0.5%** - Good, monitor but no urgent action
- **0.5-5%** - Warning, investigate root cause
- **>5%** - Critical, immediate action needed

**Common causes:**
- 503 Service Unavailable → Resource exhaustion
- 504 Gateway Timeout → Database too slow
- Connection refused → Too many connections
- 500 Internal Server Error → Application bug under load

**Example Analysis:**

```
Baseline: 0.0% error rate ✓
Ramp (100 users): 0.2% ✓
Ramp (500 users): 1.5% ⚠
Ramp (1000 users): 15% ❌

Action: System breaks at ~500 users, max capacity reached
```

### Throughput (Requests Per Second)

**What it measures:** How many requests the server handles per second.

**Healthy patterns:**
- Linear increase during ramp test
- Plateaus at maximum capacity
- Consistent during spike recovery

**Unhealthy patterns:**
- Decreases under increasing load → Serious bottleneck
- Spikes → Queuing/caching behavior
- Zero → Complete failure

**Example Analysis:**

```
Baseline (10 users): 150 RPS ✓
Ramp (100 users): 1200 RPS ✓
Ramp (500 users): 5000 RPS ✓
Ramp (1000 users): 5100 RPS → Plateaued ⚠
Ramp (2000 users): 5050 RPS → Declining ❌

Action: Maximum capacity is ~1000 concurrent users
```

### Database Metrics

**Query Time (DB Time in response breakdown):**
- Increases sharply → Database bottleneck
- Stable → Database performing well
- Drops suddenly → Query cache working

**Connection Pool Usage:**
- >80% → Risk of pool exhaustion
- 100% → Connections unavailable, requests queued
- Average <50% → Good headroom

**Lock Wait Time:**
- Increases → Lock contention, slow queries blocking others
- Zero → No locking issues
- Spikes → Particular query causing issues

**Example Analysis:**

```
Request time breakdown:
- DNS: 2ms (fast)
- Connect: 5ms (good)
- TLS: 8ms (expected)
- Request: 20ms (good)
- Wait: 500ms ❌ (Database!)
- Receive: 15ms (ok)

Total: 550ms

Root cause: Database waiting time is 91% of total response time
Action: Optimize slow queries, add indexes, increase connection pool
```

## Test-Specific Analysis

### Baseline Test Analysis

**Purpose:** Establish normal performance under light load

**What to look for:**
1. All response times under target (P95 < 500ms)
2. Zero errors
3. Stable metrics over 5-minute duration
4. Even distribution of endpoints

**Example Report:**

```
✓ P50: 42ms (Target: <100ms)
✓ P95: 145ms (Target: <500ms)
✓ P99: 298ms (Target: <1000ms)
✓ Error rate: 0.0%
✓ Throughput: 120 RPS
✓ Database time: 35ms avg
```

**Actions if baseline fails:**
- Find the problematic endpoint
- Profile that endpoint locally
- Check for N+1 queries
- Verify database schema has proper indexes

### Ramp Test Analysis

**Purpose:** Identify where performance degrades under increasing load

**Key questions:**
1. Where do errors start appearing?
2. At what user count does latency spike?
3. Where does throughput plateau?
4. What resource hits its limit first?

**Healthy pattern:**

```
Users  | P95 Response | Error Rate | Throughput
-------|--------------|------------|------------
100    | 150ms        | 0.0%       | 1200 RPS
500    | 200ms        | 0.0%       | 6000 RPS
1000   | 350ms        | 0.0%       | 12000 RPS
2000   | 500ms        | 0.1%       | 12200 RPS (plateau)
5000   | 1200ms       | 2%         | 12100 RPS

Analysis: System handles 2000 users comfortably, starts struggling at 5000
```

**Unhealthy pattern:**

```
Users  | P95 Response | Error Rate | Throughput
-------|--------------|------------|------------
100    | 150ms        | 0.0%       | 1200 RPS
500    | 800ms ❌     | 1.2% ❌    | 5800 RPS
1000   | 2500ms ❌    | 15% ❌     | 5200 RPS
2000   | N/A (crash)  | 50%+ ❌    | 0 RPS ❌

Analysis: System crashes at ~800 users, immediate action needed
```

**Actions by limiting resource:**

If **CPU** is the limit:
- Optimize hot code paths
- Add caching
- Use async jobs
- Horizontal scaling

If **Memory** is the limit:
- Reduce memory footprint
- Optimize queries
- Cache to Redis
- Increase server memory

If **Database** is the limit:
- Add indexes
- Optimize queries
- Connection pooling
- Read replicas

If **Disk I/O** is the limit:
- Move to faster storage
- Reduce logging
- Cache more aggressively
- Use CDN for static assets

### Stress Test Analysis

**Purpose:** Find the breaking point

**Expected behavior:**
1. Graceful degradation under increasing load
2. System still responsive even under stress
3. Partial success better than complete failure
4. Recovery after load reduces

**Healthy stress test:**

```
0-1000 users: Normal, <1% errors
1000-2000 users: Degraded but responsive, 5-10% errors
2000-5000 users: Very slow, 30-50% errors
5000+ users: Mostly failures, but not crashed

→ Breaking point: 5000 users
→ Recommended max: 3000 users (50% safety margin)
```

**Unhealthy stress test:**

```
100-500 users: Normal
500-600 users: Sudden crash
All requests fail with 503/504

→ No graceful degradation
→ Immediate action needed
```

**Recovery indicators:**
- After load drops, response times return to baseline
- Error rate returns to zero
- Throughput stable
- No cascading failures

**Common stress test findings:**

1. **Memory grows over time** → Memory leak
2. **Response times don't recover** → Resource held after request
3. **Errors stay high after load drops** → State corruption
4. **Database connections accumulate** → Not returning connections to pool

### Spike Test Analysis

**Purpose:** Verify quick recovery from sudden traffic spikes

**Good spike recovery:**

```
Baseline: 100 users, P95: 150ms, Errors: 0%
  ↓
Spike to 500 users (10 seconds)
Peak: P95: 400ms, Errors: 5%
  ↓
Recovery (60 seconds)
Back to baseline: P95: 160ms, Errors: 0%

→ System recovers within spike window
```

**Bad spike recovery:**

```
Baseline: 100 users, P95: 150ms, Errors: 0%
  ↓
Spike to 500 users (10 seconds)
Peak: P95: 2000ms, Errors: 25%
  ↓
Recovery attempt (60 seconds)
Still elevated: P95: 800ms, Errors: 5%
  ↓
After 5 minutes: P95: 400ms, Errors: 2%

→ Slow recovery, potential cascading failures
```

**Spike analysis checklist:**
- [ ] Can system handle 2x normal traffic?
- [ ] Can system handle 5x normal traffic?
- [ ] Recovery time < 1 minute?
- [ ] No lasting impact after spike?
- [ ] Queue processing continues during spike?

### Chaos Test Analysis

**Purpose:** Verify resilience to failures

**Expected behaviors:**

1. **Latency injection** - Response times increase but succeed
2. **DB pool exhaustion** - Some requests queue, eventual success
3. **Queue backlog** - Jobs accumulate, clear when consumer restarts
4. **Intermittent failures** - Partial failures, quick retry success
5. **Service kill** - Brief downtime, auto-recovery

**Good chaos results:**

```
Scenario: Latency Injection (3s delay)
- Requests complete (no timeout)
- Response time: 3000ms+
- Success rate: 100%
- Recovery: Immediate when latency removed

Scenario: Queue backlog
- Jobs accepted: 100%
- Processing delayed: Yes
- Jobs processed after recovery: 100%
- Data consistency: Maintained

Scenario: Intermittent failures
- Partial failure: 30%
- Automatic retry success: 95%
- No data corruption: Confirmed
```

**Bad chaos results:**

```
Scenario: Service kill
- Downtime: 30+ seconds ❌
- Automatic restart: No ❌
- Connection reuse after restart: No ❌

Scenario: DB pool exhaustion
- Requests fail immediately: ❌
- No queuing: ❌
- No graceful degradation: ❌
```

## Performance Optimization Guide

### Step 1: Identify the Bottleneck

Use your test results to find what's slow:

```bash
# Database bottleneck indicators:
- DB wait time > 50% of request time
- Query times spike under load
- Connection pool at 80%+

# CPU bottleneck indicators:
- CPU usage at 80%+
- Response time increases proportional to users
- Throughput plateaus

# Memory bottleneck indicators:
- Memory usage grows with load and doesn't decrease
- GC pauses increase under load
- Response times increase sporadically
```

### Step 2: Optimize That Resource

**For Database:**
```bash
# Find slow queries
php artisan tinker
>>> \Log::listen(function($query) { if($query->time > 100) dump($query); });

# Add indexes
php artisan make:migration add_indexes_to_invoices_table

# Use eager loading
Invoice::with('customer', 'items')->get();

# Implement caching
Cache::remember('invoices:'.$company_id, 3600, function() {
    return Invoice::where('company_id', $company_id)->get();
});
```

**For CPU:**
```bash
# Identify hot code with profiling
# Reduce complexity in hot paths
# Implement async jobs
Queue::dispatch(new ProcessInvoice($invoice));

# Cache expensive computations
Cache::remember('dashboard:stats', 300, fn() => calculateStats());
```

**For Memory:**
```bash
# Check for memory leaks
php artisan tinker
>>> memory_get_peak_usage(); // Before and after operation

# Reduce object retention
unset($largeArray);
gc_collect_cycles();

# Stream large datasets
Invoice::all()->chunk(100)->each(function($invoices) {
    // Process chunk
});
```

### Step 3: Measure Improvement

```bash
# Before optimization
./tests/load/run-load-tests.sh baseline
# Results: P95: 500ms, Error rate: 0.1%

# Apply optimization

# After optimization
./tests/load/run-load-tests.sh baseline
# Results: P95: 250ms, Error rate: 0.05%

# Improvement: 50% faster, 2x more reliable
```

## Trend Analysis

### Establishing Baselines

**Week 1:** Run all tests to establish baselines
```
baseline:  P95: 450ms, Error: 0.05%
ramp:      Max users: 1500
stress:    Breaking point: 3000 users
spike:     Recovery time: 45 seconds
```

**Weekly:** Run baseline test to track trends
```
Week 1: P95: 450ms
Week 2: P95: 465ms (+3%)
Week 3: P95: 485ms (+8%)
Week 4: P95: 510ms (+13%) ⚠ Investigate
```

**Monthly:** Run full test suite for deep analysis

### Red Flags

**Watch for:**
- Response times increasing trend (slow degradation)
- Error rate increasing even under same load
- Throughput decreasing over time
- Memory usage never decreasing
- Database connections accumulating

## Reporting

### Executive Summary

```markdown
# Performance Report - August 2026

## Key Findings
- System handles 1500 concurrent users reliably
- Response times excellent (P95: 150ms)
- Error rate near zero (0.04%)
- One database bottleneck identified

## Capacity Recommendations
- Current: 1500 users
- Scaling needed: At 2500 users
- Timeline: 3-6 months based on growth rate

## Action Items
1. Optimize invoice listing query (estimate: 20% improvement)
2. Implement caching layer (estimate: 50% improvement)
3. Plan database replica (timeline: Next quarter)
```

### Detailed Report

Include:
1. Test configuration and environment
2. All metrics with p50/p95/p99
3. Charts of key metrics
4. Bottleneck analysis
5. Improvement recommendations
6. Trend comparison with previous tests

### Sharing Results

```bash
# Save detailed results
k6 run tests/load/ramp.k6.js --out json=results/ramp_$(date +%Y%m%d).json

# Convert for sharing
jq '.metrics' results/ramp_*.json > report.json

# Generate HTML report (requires custom script or k6 extension)
```

## Continuous Monitoring

### Alerting

Set alerts based on baseline:
```
- If P95 > 1000ms: Warning
- If P95 > 2000ms: Critical
- If error rate > 0.5%: Warning
- If error rate > 5%: Critical
- If throughput < 100 RPS: Warning
```

### Dashboard

Create dashboard showing:
- Real-time request rate
- Response time trend (P50, P95, P99)
- Error rate
- Database connection pool usage
- Cache hit rate
- Queue depth

## References

- [Percentiles and Response Times](https://en.wikipedia.org/wiki/Percentile)
- [SLI/SLO/SLA](https://en.wikipedia.org/wiki/Service-level_agreement)
- [Performance Budget](https://timkadlec.com/remembers/2019-03-07-performance-budgets/)
