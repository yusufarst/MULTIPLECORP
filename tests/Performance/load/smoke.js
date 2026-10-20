// The load tool's smoke run against the Docker Compose stack (TEST_STRATEGY §17, TL-15; TASK-004):
// `docker compose run --rm k6 run tests/Performance/load/smoke.js`. TASK-068 sets the budgets.
/* global __ENV */
import { check } from 'k6';
import http from 'k6/http';

export const options = {
    vus: 5,
    duration: '10s',
    // The stack's certificate is issued by its throwaway local authority (docker/certs).
    insecureSkipTLSVerify: true,
    thresholds: { http_req_failed: ['rate==0'], checks: ['rate==1'] },
};

export default function () {
    const response = http.get(`${__ENV.K6_BASE_URL}/robots.txt`);

    check(response, { 'answers 200': (r) => r.status === 200 });
}
