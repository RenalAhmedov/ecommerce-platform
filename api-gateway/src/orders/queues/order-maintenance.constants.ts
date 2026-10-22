import type { JobsOptions } from 'bullmq';

export const ORDER_MAINTENANCE_QUEUE = 'order-maintenance';
export const EXPIRE_STALE_ORDERS_JOB = 'expire-stale-orders';
export const SWEEP_CONFIRMATION_EMAILS_JOB = 'sweep-confirmation-emails';

export const MAINTENANCE_INTERVAL_MS = 5 * 60_000;

export const MAINTENANCE_JOB_OPTIONS: JobsOptions = {
  removeOnComplete: { count: 100 },
  removeOnFail: { count: 100 },
};
