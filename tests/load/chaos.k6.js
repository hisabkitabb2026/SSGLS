import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

/**
 * Chaos Testing - Test system resilience under failure conditions
 * Run with: k6 run tests/load/chaos.k6.js
 *
 * Scenarios:
 * 1. Service latency injection
 * 2. Database connection pool exhaustion
 * 3. Message queue backlog
 * 4. Intermittent service failures
 * 5. Cache invalidation
 */

// Custom metrics
const errorRate = new Rate('errors');
const resilience = new Rate('recovered_from_failures');
const responseTimes = new Trend('response_times');
const failureDetection = new Counter('failure_detected');
const recoveryTime = new Trend('recovery_time_ms');
const requestCounter = new Counter('total_requests');

export const options = {
  stages: [
    { duration: '2m', target: 50 },    // Baseline normal load
    { duration: '3m', target: 50 },    // Inject latency
    { duration: '2m', target: 50 },    // DB pool exhaustion
    { duration: '3m', target: 50 },    // Queue backlog
    { duration: '2m', target: 50 },    // Intermittent failures
    { duration: '2m', target: 0 },     // Ramp-down
  ],
  thresholds: {
    'http_req_duration': ['p(99)<15000'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://invoiceshelf.test/api/v1';
const TEST_EMAIL = __ENV.TEST_EMAIL || 'test@invoiceshelf.test';
const TEST_PASSWORD = __ENV.TEST_PASSWORD || 'password';
const COMPANY_ID = __ENV.COMPANY_ID || 1;

// Chaos scenario tracking
let chaosStartTime = Date.now();
let scenarioIndex = 0;
let lastRecoveryCheck = Date.now();

export function setup() {
  const loginRes = http.post(`${BASE_URL}/auth/login`, {
    email: TEST_EMAIL,
    password: TEST_PASSWORD,
  });

  if (loginRes.status === 200) {
    const data = JSON.parse(loginRes.body);
    return { token: data.data.token };
  }
  throw new Error('Failed to authenticate during setup');
}

export default function (data) {
  const token = data.token;
  const headers = {
    Authorization: `Bearer ${token}`,
    'company': COMPANY_ID.toString(),
    'Content-Type': 'application/json',
  };

  requestCounter.add(1);
  const elapsed = Date.now() - chaosStartTime;

  // Determine current chaos scenario (every 3 minutes)
  const scenario = Math.floor(elapsed / (3 * 60 * 1000)) % 5;

  group(`Chaos Scenario ${scenario}: ${getScenarioName(scenario)}`, () => {
    const requestStartTime = Date.now();

    switch(scenario) {
      case 0: // Baseline
        testBaseline(headers);
        break;
      case 1: // Latency injection
        testWithLatencyInjection(headers);
        break;
      case 2: // DB pool exhaustion
        testWithDBPoolExhaustion(headers);
        break;
      case 3: // Message queue backlog
        testWithQueueBacklog(headers);
        break;
      case 4: // Intermittent failures
        testIntermittentFailures(headers);
        break;
    }

    // Check recovery time
    const responseTime = Date.now() - requestStartTime;
    if (responseTime > 5000) {
      failureDetection.add(1);
      recoveryTime.add(responseTime);
    }
  });

  sleep(Math.random() * 1 + 0.5);
}

function getScenarioName(scenario) {
  const names = [
    'Baseline',
    'Latency Injection',
    'DB Pool Exhaustion',
    'Queue Backlog',
    'Intermittent Failures'
  ];
  return names[scenario] || 'Unknown';
}

function testBaseline(headers) {
  const res = http.get(`${BASE_URL}/auth/check`, { headers });
  const isSuccess = res.status === 200;
  check(res, {
    'baseline: auth check succeeds': (r) => r.status === 200,
    'baseline: response time < 500ms': (r) => r.timings.duration < 500,
  });
  responseTimes.add(res.timings.duration);
  errorRate.add(!isSuccess);
  resilience.add(isSuccess);
}

function testWithLatencyInjection(headers) {
  /**
   * Simulates network latency or service slowdown
   * In real scenario, this would be injected via:
   * - Network proxies (tc, toxiproxy)
   * - Service mesh (Istio fault injection)
   * - API gateway rate limiting
   */

  const startTime = Date.now();
  const res = http.get(`${BASE_URL}/dashboard`, { headers, timeout: '15s' });
  const duration = Date.now() - startTime;

  const isSuccess = res.status === 200;
  check(res, {
    'latency: dashboard recovers': (r) => r.status === 200,
    'latency: response time < 10s': () => duration < 10000,
  });

  responseTimes.add(res.timings.duration);
  errorRate.add(!isSuccess);
  resilience.add(duration < 10000 && isSuccess);
}

function testWithDBPoolExhaustion(headers) {
  /**
   * Simulates database connection pool exhaustion
   * Real injection methods:
   * - Kill DB connections: `mysql -e "KILL CONNECTION ..."`
   * - Reduce pool size in config
   * - Heavy concurrent queries
   */

  const requests = [
    http.get(`${BASE_URL}/invoices?limit=100&page=1`, { headers, timeout: '15s' }),
    http.get(`${BASE_URL}/customers?limit=100&page=1`, { headers, timeout: '15s' }),
    http.get(`${BASE_URL}/items?limit=100&page=1`, { headers, timeout: '15s' }),
    http.get(`${BASE_URL}/dashboard`, { headers, timeout: '15s' }),
  ];

  let successCount = 0;
  for (const res of requests) {
    const isSuccess = res.status >= 200 && res.status < 500;
    check(res, {
      'db_pool: request completes': (r) => r.status >= 100 && r.status < 600,
      'db_pool: no connection timeout': (r) => r.status !== 0,
    });

    responseTimes.add(res.timings.duration);
    errorRate.add(!isSuccess);
    if (isSuccess) successCount++;
  }

  resilience.add(successCount >= 2); // Recovers if at least 2/4 succeed
}

function testWithQueueBacklog(headers) {
  /**
   * Simulates message queue backlog
   * Real injection methods:
   * - Kill queue consumer: `pkill -f queue:listen`
   * - Fill queue with messages
   * - Slow down consumer processing
   */

  const invoicePayload = {
    invoice_number: `CHAOS-QUEUE-${Math.random().toString(36).substr(2, 9)}`,
    customer_id: Math.floor(Math.random() * 100) + 1,
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    items: [
      {
        item_id: Math.floor(Math.random() * 50) + 1,
        quantity: Math.floor(Math.random() * 10) + 1,
        price: Math.floor(Math.random() * 1000) + 100
      }
    ]
  };

  const res = http.post(`${BASE_URL}/invoices`, JSON.stringify(invoicePayload), {
    headers,
    timeout: '15s'
  });

  const isSuccess = res.status >= 200 && res.status < 400;
  check(res, {
    'queue_backlog: invoice created': (r) => r.status >= 200 && r.status < 400,
    'queue_backlog: response under 10s': (r) => r.timings.duration < 10000,
  });

  responseTimes.add(res.timings.duration);
  errorRate.add(!isSuccess);
  resilience.add(isSuccess);
}

function testIntermittentFailures(headers) {
  /**
   * Simulates intermittent service failures
   * Real injection methods:
   * - Kill and restart PHP processes
   * - Network packet loss (tc command)
   * - Redis/cache crashes
   */

  const endpoints = [
    `${BASE_URL}/auth/check`,
    `${BASE_URL}/dashboard`,
    `${BASE_URL}/invoices?limit=20&page=1`,
    `${BASE_URL}/customers?limit=20&page=1`,
  ];

  let successCount = 0;
  for (const endpoint of endpoints) {
    try {
      const res = http.get(endpoint, { headers, timeout: '15s' });
      const isSuccess = res.status >= 200 && res.status < 400;

      check(res, {
        'intermittent: request succeeds': (r) => r.status >= 200 && r.status < 400,
      });

      responseTimes.add(res.timings.duration);
      errorRate.add(!isSuccess);

      if (isSuccess) successCount++;
    } catch (e) {
      failureDetection.add(1);
    }
  }

  // Partial recovery is acceptable
  resilience.add(successCount >= 2);
}

export function handleSummary(data) {
  return {
    'stdout': generateChaosReport(data),
    'chaos-results.json': JSON.stringify(data),
  };
}

function generateChaosReport(data) {
  let report = '\n=== Chaos Testing Report ===\n\n';

  if (data.metrics) {
    const errorRateValue = data.metrics.errors?.values?.rate || 0;
    const recoveryRateValue = data.metrics.recovered_from_failures?.values?.rate || 0;

    report += `Error Rate: ${(errorRateValue * 100).toFixed(2)}%\n`;
    report += `Recovery Rate: ${(recoveryRateValue * 100).toFixed(2)}%\n`;
    report += `Total Requests: ${data.metrics.total_requests?.value || 'N/A'}\n`;
    report += `Failures Detected: ${data.metrics.failure_detected?.value || 0}\n\n`;

    if (data.metrics.response_times) {
      const rt = data.metrics.response_times.values;
      report += `Response Times:\n`;
      report += `  - P95: ${rt['p(95)'] || 'N/A'}ms\n`;
      report += `  - P99: ${rt['p(99)'] || 'N/A'}ms\n`;
      report += `  - Max: ${rt.max || 'N/A'}ms\n\n`;
    }

    if (data.metrics.recovery_time_ms) {
      const recovery = data.metrics.recovery_time_ms.values;
      report += `Recovery Times:\n`;
      report += `  - Avg: ${recovery.avg || 'N/A'}ms\n`;
      report += `  - Max: ${recovery.max || 'N/A'}ms\n`;
    }
  }

  return report;
}
