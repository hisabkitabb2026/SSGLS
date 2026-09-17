import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

/**
 * Spike Test - Sudden increase in load
 * Simulates unexpected traffic spikes
 * Run with: k6 run tests/load/spike.k6.js
 */

// Custom metrics
const errorRate = new Rate('errors');
const successRate = new Rate('success');
const responseTimes = new Trend('response_times');
const spikeRecoveryTime = new Trend('spike_recovery_ms');
const requestCounter = new Counter('total_requests');
const failedRequests = new Counter('failed_requests');

export const options = {
  stages: [
    { duration: '1m', target: 100 },    // Normal load
    { duration: '10s', target: 500 },   // Spike 1: sudden jump to 500
    { duration: '1m', target: 100 },    // Recovery
    { duration: '10s', target: 1000 },  // Spike 2: sudden jump to 1000
    { duration: '1m', target: 100 },    // Recovery
    { duration: '10s', target: 2000 },  // Spike 3: sudden jump to 2000
    { duration: '1m', target: 100 },    // Recovery
    { duration: '1m', target: 0 },      // Ramp-down
  ],
  thresholds: {
    'http_req_duration': ['p(95)<3000'],
    'http_req_failed': ['rate<0.3'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://invoiceshelf.test/api/v1';
const TEST_EMAIL = __ENV.TEST_EMAIL || 'test@invoiceshelf.test';
const TEST_PASSWORD = __ENV.TEST_PASSWORD || 'password';
const COMPANY_ID = __ENV.COMPANY_ID || 1;

// Track spike transitions
let lastStageTime = Date.now();
let inSpike = false;

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
  const requestStartTime = Date.now();

  // Detect spike transitions and measure recovery
  const currentTime = Date.now();
  const timeSinceLastStage = currentTime - lastStageTime;

  if (timeSinceLastStage < 12000) { // During spike or recovery
    if (timeSinceLastStage < 10000) {
      inSpike = true;
    } else if (inSpike && timeSinceLastStage >= 10000) {
      inSpike = false;
    }
  }

  group(inSpike ? 'Spike Load' : 'Normal Load', () => {
    // Vary operations based on spike state
    let res;

    if (inSpike) {
      // During spike, hammer the API
      const spikeOps = [
        http.get(`${BASE_URL}/dashboard`, { headers }),
        http.get(`${BASE_URL}/invoices?limit=50&page=1`, { headers }),
        http.get(`${BASE_URL}/customers?limit=50&page=1`, { headers }),
      ];

      for (const spikeRes of spikeOps) {
        const isSuccess = spikeRes.status >= 200 && spikeRes.status < 400;
        check(spikeRes, {
          'spike request succeeds': (r) => r.status >= 200 && r.status < 400,
          'spike response time acceptable': (r) => r.timings.duration < 5000,
        });

        responseTimes.add(spikeRes.timings.duration);
        errorRate.add(!isSuccess);
        successRate.add(isSuccess);

        if (!isSuccess) {
          failedRequests.add(1);
        }

        // Measure recovery time during spike
        const recoveryTime = Date.now() - requestStartTime;
        spikeRecoveryTime.add(spikeRes.timings.duration);
      }
    } else {
      // Normal operation
      res = http.get(`${BASE_URL}/auth/check`, { headers });
      const isSuccess = res.status >= 200 && res.status < 400;
      check(res, {
        'normal request succeeds': (r) => r.status >= 200 && r.status < 400,
      });

      responseTimes.add(res.timings.duration);
      errorRate.add(!isSuccess);
      successRate.add(isSuccess);
    }
  });

  sleep(Math.random() * 1 + 0.5);
}
