import type { JobsOptions } from 'bullmq';

export const ORDER_EMAILS_QUEUE = 'order-emails';
export const ORDER_CONFIRMATION_JOB = 'order-confirmation';

export const ORDER_EMAIL_JOB_OPTIONS: JobsOptions = {
  attempts: 5,
  backoff: { type: 'exponential', delay: 5_000 },
  removeOnComplete: { age: 7 * 24 * 3600 },
  removeOnFail: { age: 30 * 24 * 3600 },
};
