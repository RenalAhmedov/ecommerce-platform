export interface HealthReport {
  status: 'ok' | 'degraded' | 'down';
  checks: { database: 'up' | 'down'; redis: 'up' | 'down' };
}
