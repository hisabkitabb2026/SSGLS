import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

/**
 * Ramp Test with 1000 Concurrent Users over 10 Minutes
 * Tests gradual scaling from 0 to 1000 users
 * Run with: k6 run tests/load/ramp.k6.js
 */

// Custom metrics
const errorRate = new Rate('errors');
const successRate = new Rate('success');
const responseTimes = new Trend('response_times');
const invoiceCreateTimes = new Trend('invoice_create_times');
const dbQueryTime = new Trend('db_query_time');
const requestCounter = new Counter('total_requests');
const timeoutCounter = new Counter('timeouts');

export const options = {
  stages: [
    { duration: '1m', target: 100 },    // Ramp-up: 0-100 users over 1 minute
    { duration: '2m', target: 500 },    // Ramp-up: 100-500 users over 2 minutes
    { duration: '2m', target: 1000 },   // Ramp-up: 500-1000 users over 2 minutes
    { duration: '3m', target: 1000 },   // Stay at 1000 users for 3 minutes
    { duration: '2m', target: 0 },      // Ramp-down: 1000-0 users over 2 minutes
  ],
  thresholds: {
    'http_req_duration': ['p(95)<2000', 'p(99)<5000'],
    'http_req_failed': ['rate<0.2'],
    'errors': ['rate<0.15'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://invoiceshelf.test/api/v1';
const TEST_EMAIL = __ENV.TEST_EMAIL || 'test@invoiceshelf.test';
const TEST_PASSWORD = __ENV.TEST_PASSWORD || 'password';
const COMPANY_ID = __ENV.COMPANY_ID || 1;

export function setup() {
  const loginRes = http.post(`${BASE_URL}/auth/login`, {
    email: TEST_EMAIL,
    password: TEST_PASSWORD,
  });

  check(loginRes, {
    'setup login successful': (r) => r.status === 200,
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

  // Simulate mixed workload
  const operations = [
    'auth_check',
    'dashboard',
    'invoices_list',
    'customers_list',
    'items_list',
    'invoice_create',
    'estimates_list'
  ];

  const operation = operations[Math.floor(Math.random() * operations.length)];

  group(`Operation: ${operation}`, () => {
    let res;
    const startTime = new Date();

    switch(operation) {
      case 'auth_check':
        res = http.get(`${BASE_URL}/auth/check`, { headers });
        break;

      case 'dashboard':
        res = http.get(`${BASE_URL}/dashboard`, { headers });
        break;

      case 'invoices_list':
        res = http.get(`${BASE_URL}/invoices?limit=50&page=${Math.floor(Math.random() * 10) + 1}`, { headers });
        break;

      case 'customers_list':
        res = http.get(`${BASE_URL}/customers?limit=50&page=${Math.floor(Math.random() * 10) + 1}`, { headers });
        break;

      case 'items_list':
        res = http.get(`${BASE_URL}/items?limit=50&page=${Math.floor(Math.random() * 10) + 1}`, { headers });
        break;

      case 'invoice_create':
        const invoicePayload = {
          invoice_number: `INV-${Math.random().toString(36).substr(2, 9)}`,
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
        res = http.post(`${BASE_URL}/invoices`, JSON.stringify(invoicePayload), { headers });
        invoiceCreateTimes.add(res.timings.duration);
        break;

      case 'estimates_list':
        res = http.get(`${BASE_URL}/estimates?limit=50&page=${Math.floor(Math.random() * 10) + 1}`, { headers });
        break;

      default:
        res = http.get(`${BASE_URL}/auth/check`, { headers });
    }

    const duration = new Date() - startTime;
    const isSuccess = res.status >= 200 && res.status < 400;

    check(res, {
      'status is 2xx or 3xx': (r) => r.status >= 200 && r.status < 400,
      'response time < 5s': (r) => r.timings.duration < 5000,
    });

    responseTimes.add(res.timings.duration);
    dbQueryTime.add(res.timings.waiting);
    errorRate.add(!isSuccess);
    successRate.add(isSuccess);

    if (res.timings.duration > 5000) {
      timeoutCounter.add(1);
    }
  });

  sleep(Math.random() * 2 + 0.5); // Sleep 0.5-2.5 seconds
}
