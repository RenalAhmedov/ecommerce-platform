import type { MailService } from '../../../mail/mail.service.js';
import type { OrdersRepository } from '../../orders.repository.js';
import { OrderConfirmationService } from '../../services/order-confirmation.service.js';

describe('OrderConfirmationService', () => {
  let repository: { findForConfirmation: ReturnType<typeof vi.fn> };
  let mail: { send: ReturnType<typeof vi.fn> };
  let service: OrderConfirmationService;

  beforeEach(() => {
    repository = { findForConfirmation: vi.fn() };
    mail = { send: vi.fn() };
    service = new OrderConfirmationService(
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

    await service.send(10);

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

    await expect(service.send(10)).resolves.toBeUndefined();
    expect(mail.send).not.toHaveBeenCalled();
  });

  it('lets SMTP failures escape so the queue retries the job', async () => {
    repository.findForConfirmation.mockResolvedValue({
      orderNumber: 'ORD-1',
      total: '10.00',
      user: { name: 'Ada', email: 'ada@example.test' },
      shippingAddress: null,
      items: [],
    });
    mail.send.mockRejectedValue(new Error('ECONNREFUSED'));

    await expect(service.send(10)).rejects.toThrow('ECONNREFUSED');
  });
});
