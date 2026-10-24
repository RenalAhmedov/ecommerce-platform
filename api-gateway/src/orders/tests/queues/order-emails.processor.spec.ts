import type { Job } from 'bullmq';
import type { OrderConfirmationJobData } from '../../queues/interfaces/order-confirmation-job.interface.js';
import { OrderEmailsProcessor } from '../../queues/order-emails.processor.js';
import type { OrderConfirmationService } from '../../services/order-confirmation.service.js';

describe('OrderEmailsProcessor', () => {
  it('sends the confirmation of the order named in the job', async () => {
    const confirmations = { send: vi.fn().mockResolvedValue(undefined) };
    const processor = new OrderEmailsProcessor(
      confirmations as unknown as OrderConfirmationService,
    );

    await processor.process({
      data: { orderId: 10 },
    } as Job<OrderConfirmationJobData>);

    expect(confirmations.send).toHaveBeenCalledWith(10);
  });
});
