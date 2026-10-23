import type { Job } from 'bullmq';
import type { MailService } from '../../../mail/mail.service.js';
import type { OrdersRepository } from '../../orders.repository.js';
import type { OrderConfirmationJobData } from '../../queues/interfaces/order-confirmation-job.interface.js';
import { OrderEmailsProcessor } from '../../queues/order-emails.processor.js';

describe('OrderEmailsProcessor', () => {
  let repository: { findForConfirmation: ReturnType<typeof vi.fn> };
  let mail: { send: ReturnType<typeof vi.fn> };
  let processor: OrderEmailsProcessor;

  const job = { data: { orderId: 10 } } as Job<OrderConfirmationJobData>;

  beforeEach(() => {
    repository = { findForConfirmation: vi.fn() };
    mail = { send: vi.fn() };
    processor = new OrderEmailsProcessor(
      repository as unknown as OrdersRepository,
      mail as unknown as MailService,
    );
  });

  it('emails the confirmation to the customer who placed the order', async () => {
    repository.findForConfirmation.mockResolvedValue({
      orderNumber: 'ORD-1',
      total: '10.00',
      user: { name: 'Ada', email: 'ada@example.test' },
      shippingAddress: null,
      items: [],
    });

    await processor.process(job);

    expect(repository.findForConfirmation).toHaveBeenCalledWith(10);
    expect(mail.send).toHaveBeenCalledWith(
      expect.objectContaining({
        to: 'ada@example.test',
        subject: 'Your order ORD-1 is confirmed',
      }),
    );
  });

  it('completes without sending when the order no longer exists', async () => {
    repository.findForConfirmation.mockResolvedValue(null);

    await expect(processor.process(job)).resolves.toBeUndefined();
    expect(mail.send).not.toHaveBeenCalled();
  });

  it('lets SMTP failures escape so BullMQ retries the job', async () => {
    repository.findForConfirmation.mockResolvedValue({
      orderNumber: 'ORD-1',
      total: '10.00',
      user: { name: 'Ada', email: 'ada@example.test' },
      shippingAddress: null,
      items: [],
    });
    mail.send.mockRejectedValue(new Error('ECONNREFUSED'));

    await expect(processor.process(job)).rejects.toThrow('ECONNREFUSED');
  });
});
