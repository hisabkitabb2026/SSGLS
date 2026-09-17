import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend, Counter, Gauge } from 'k6/metrics';

/**
 * Baseline Performance Test
 * Establishes baseline metrics for the application
 * Run with: k6 run tests/load/baseline.k6.js
 */

// Custom metrics
const errorRate = new Rate('errors');
const successRate = new Rate('success');
const responseTimes = new Trend('response_times');
const loginTimes = new Trend('login_times');
const dashboardTimes = new Trend('dashboard_times');
const invoiceListTimes = new Trend('invoice_list_times');
const invoiceCreateTimes = new Trend('invoice_create_times');
const customerListTimes = new Trend('customer_list_times');
const dbQueryTime = new Trend('db_query_time');
const activeConnections = new Gauge('active_connections');
const requestCounter = new Counter('total_requests');

export const options = {
  stages: [
    { duration: '2m', target: 10 },   // Ramp-up: 0-10 users over 2 minutes
    { duration: '5m', target: 10 },   // Stay at 10 users for 5 minutes
    { duration: '2m', target: 0 },    // Ramp-down: 10-0 users over 2 minutes
  ],
  thresholds: {
    'http_req_duration': ['p(95)<500', 'p(99)<1000'],
    'http_req_failed': ['rate<0.1'],
    'errors': ['rate<0.05'],
  },
  ext: {
    loadimpact: {
      projectID: 3496033,
      name: 'InvoiceShelf Baseline Test'
    }
  }
};

const BASE_URL = __ENV.BASE_URL || 'http://invoiceshelf.test/api/v1';
const TEST_EMAIL = __ENV.TEST_EMAIL || 'test@invoiceshelf.test';
const TEST_PASSWORD = __ENV.TEST_PASSWORD || 'password';
const COMPANY_ID = __ENV.COMPANY_ID || 1;

// Global variable to store auth token
let authToken = '';

export function setup() {
  // Login once during setup and get token
  const loginRes = http.post(`${BASE_URL}/auth/login`, {
    email: TEST_EMAIL,
    password: TEST_PASSWORD,
  });

  check(loginRes, {
    'login successful': (r) => r.status === 200,
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

  activeConnections.set(1);
  requestCounter.add(1);

  group('Authentication Check', () => {
    const res = http.get(`${BASE_URL}/auth/check`, { headers });
    const isSuccess = res.status === 200;
    check(res, {
      'auth check status is 200': (r) => r.status === 200,
      'auth check returns token': (r) => r.body.includes('token') || r.body.includes('user'),
    });
    responseTimes.add(res.timings.duration);
    errorRate.add(!isSuccess);
    successRate.add(isSuccess);
  });

  sleep(1);

  group('Dashboard Metrics', () => {
    const res = http.get(`${BASE_URL}/dashboard`, { headers });
    const isSuccess = res.status === 200;
    check(res, {
      'dashboard status is 200': (r) => r.status === 200,
      'dashboard has data': (r) => r.body.includes('data') || r.body.includes('invoice'),
    });
    dashboardTimes.add(res.timings.duration);
    responseTimes.add(res.timings.duration);
    errorRate.add(!isSuccess);
    successRate.add(isSuccess);
  });

  sleep(1);

  group('List Invoices', () => {
    const res = http.get(`${BASE_URL}/invoices?limit=20&page=1`, { headers });
    const isSuccess = res.status === 200;
    check(res, {
      'invoices list status is 200': (r) => r.status === 200,
      'invoices list has data': (r) => r.body.includes('data'),
    });
    invoiceListTimes.add(res.timings.duration);
    responseTimes.add(res.timings.duration);
    errorRate.add(!isSuccess);
    successRate.add(isSuccess);
  });

  sleep(1);

  group('List Customers', () => {
    const res = http.get(`${BASE_URL}/customers?limit=20&page=1`, { headers });
    const isSuccess = res.status === 200;
    check(res, {
      'customers list status is 200': (r) => r.status === 200,
      'customers list has data': (r) => r.body.includes('data'),
    });
    customerListTimes.add(res.timings.duration);
    responseTimes.add(res.timings.duration);
    errorRate.add(!isSuccess);
    successRate.add(isSuccess);
  });

  sleep(1);

  group('Get Currencies', () => {
    const res = http.get(`${BASE_URL}/currencies`, { headers });
    const isSuccess = res.status === 200;
    check(res, {
      'currencies status is 200': (r) => r.status === 200,
    });
    responseTimes.add(res.timings.duration);
    errorRate.add(!isSuccess);
    successRate.add(isSuccess);
  });

  sleep(1);

  group('List Items', () => {
    const res = http.get(`${BASE_URL}/items?limit=20&page=1`, { headers });
    const isSuccess = res.status === 200;
    check(res, {
      'items list status is 200': (r) => r.status === 200,
    });
    responseTimes.add(res.timings.duration);
    errorRate.add(!isSuccess);
    successRate.add(isSuccess);
  });

  activeConnections.set(0);
}

export function handleSummary(data) {
  return {
    'stdout': textSummary(data, { indent: ' ', enableColors: true }),
    'metrics.json': JSON.stringify(data),
  };
}

function textSummary(data, options) {
  let summary = '\n=== InvoiceShelf Baseline Test Summary ===\n\n';

  if (data.metrics) {
    const metrics = data.metrics;
    if (metrics.response_times) {
      const rt = metrics.response_times.values;
      summary += `Response Times:\n`;
      summary += `  - Min: ${rt.min || 'N/A'}ms\n`;
      summary += `  - Max: ${rt.max || 'N/A'}ms\n`;
      summary += `  - Avg: ${rt.avg || 'N/A'}ms\n`;
      summary += `  - Med: ${rt.med || 'N/A'}ms\n`;
      summary += `  - P95: ${rt['p(95)'] || 'N/A'}ms\n`;
      summary += `  - P99: ${rt['p(99)'] || 'N/A'}ms\n\n`;
    }

    if (metrics.errors) {
      summary += `Error Rate: ${(metrics.errors.values.rate * 100).toFixed(2)}%\n\n`;
    }

    if (metrics.success) {
      summary += `Success Rate: ${(metrics.success.values.rate * 100).toFixed(2)}%\n\n`;
    }
  }

  summary += `Total Requests: ${data.metrics.total_requests?.value || 'N/A'}\n`;
  return summary;
}
