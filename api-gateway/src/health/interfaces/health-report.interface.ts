export type DependencyStatus = 'up' | 'down';

export interface HealthReport {
  status: 'ok' | 'degraded' | 'down';
  checks: {
    database: DependencyStatus;
    redis: DependencyStatus;
    elasticsearch: DependencyStatus | 'disabled';
  };
}
