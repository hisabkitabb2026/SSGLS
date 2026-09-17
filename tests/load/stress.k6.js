import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

/**
 * Stress Test - Gradually increase load until system breaks
 * Run with: k6 run tests/load/stress.k6.js
 */

// Custom metrics
const errorRate = new Rate('errors');
const successRate = new Rate('success');
const responseTimes = new Trend('response_times');
const dbQueryTime = new Trend('db_query_time');
const requestCounter = new Counter('total_requests');
const crashDetector = new Counter('crashes');

export const options = {
  stages: [
    { duration: '2m', target: 100 },   // Ramp-up
    { duration: '2m', target: 200 },   // Increase
    { duration: '2m', target: 500 },   // Increase
    { duration: '2m', target: 1000 },  // Increase
    { duration: '2m', target: 2000 },  // Increase
    { duration: '2m', target: 5000 },  // Peak stress
    { duration: '2m', target: 0 },     // Ramp-down
  ],
  thresholds: {
    'http_req_duration': ['p(99)<10000'],
    'http_req_failed': ['rate<0.5'],
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

  group('Heavy Load Operations', () => {
    // Simultaneous heavy operations
    const requests = {
      'dashboard': http.get(`${BASE_URL}/dashboard`, { headers }),
      'invoices': http.get(`${BASE_URL}/invoices?limit=100&page=${Math.floor(Math.random() * 10) + 1}`, { headers }),
      'customers': http.get(`${BASE_URL}/customers?limit=100&page=${Math.floor(Math.random() * 10) + 1}`, { headers }),
      'items': http.get(`${BASE_URL}/items?limit=100&page=${Math.floor(Math.random() * 10) + 1}`, { headers }),
    };

    for (const [name, res] of Object.entries(requests)) {
      const isSuccess = res.status >= 200 && res.status < 400;
      check(res, {
        [`${name} status is 2xx or 3xx`]: (r) => r.status >= 200 && r.status < 400,
      });

      responseTimes.add(res.timings.duration);
      dbQueryTime.add(res.timings.waiting);
      errorRate.add(!isSuccess);
      successRate.add(isSuccess);

      if (res.status >= 500 || res.status === 0) {
        crashDetector.add(1);
      }
    }
  });

  // Create invoices under stress
  if (Math.random() < 0.3) { // 30% create operations
    const invoicePayload = {
      invoice_number: `STRESS-${Math.random().toString(36).substr(2, 9)}`,
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

    const createRes = http.post(`${BASE_URL}/invoices`, JSON.stringify(invoicePayload), {
      headers,
      timeout: '10s'
    });

    const isSuccess = createRes.status >= 200 && createRes.status < 400;
    check(createRes, {
      'invoice create succeeds under stress': (r) => r.status >= 200 && r.status < 400,
    });

    responseTimes.add(createRes.timings.duration);
    errorRate.add(!isSuccess);
    successRate.add(isSuccess);

    if (createRes.status >= 500 || createRes.status === 0) {
      crashDetector.add(1);
    }
  }

  sleep(Math.random() * 1);
}
