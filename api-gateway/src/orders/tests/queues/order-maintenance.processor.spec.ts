import type { Job } from 'bullmq';
import {
  EXPIRE_STALE_ORDERS_JOB,
  SWEEP_CONFIRMATION_EMAILS_JOB,
} from '../../queues/order-maintenance.constants.js';
import { OrderMaintenanceProcessor } from '../../queues/order-maintenance.processor.js';
import type { OrderConfirmationSweepService } from '../../services/order-confirmation-sweep.service.js';
import type { OrderExpiryService } from '../../services/order-expiry.service.js';

describe('OrderMaintenanceProcessor', () => {
  const expiry = { expireStaleOrders: vi.fn().mockResolvedValue(2) };
  const sweep = { sweep: vi.fn().mockResolvedValue(5) };
  const processor = new OrderMaintenanceProcessor(
    expiry as unknown as OrderExpiryService,
    sweep as unknown as OrderConfirmationSweepService,
  );

  const job = (name: string) => ({ name }) as Job;

  it('runs the stale order expiry', async () => {
    await expect(processor.process(job(EXPIRE_STALE_ORDERS_JOB))).resolves.toBe(
      2,
    );
  });

  it('runs the confirmation email sweep', async () => {
    await expect(
      processor.process(job(SWEEP_CONFIRMATION_EMAILS_JOB)),
    ).resolves.toBe(5);
  });

  it('fails loudly on an unknown job name', () => {
    expect(() => processor.process(job('something-else'))).toThrow(
      'Unknown order maintenance job "something-else"',
    );
  });
});
