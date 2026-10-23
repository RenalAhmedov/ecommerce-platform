import { Test } from '@nestjs/testing';
import { OrdersRepository } from '../../orders.repository.js';
import { OrderEmailsProducer } from '../../queues/order-emails.producer.js';
import { OrderConfirmationSweepService } from '../../services/order-confirmation-sweep.service.js';

describe('OrderConfirmationSweepService', () => {
  let sweeper: OrderConfirmationSweepService;
  let repository: { findIdsPaidSince: ReturnType<typeof vi.fn> };
  let emails: { queueConfirmations: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    vi.useFakeTimers({ now: new Date('2026-01-01T12:00:00Z') });
    repository = { findIdsPaidSince: vi.fn().mockResolvedValue([3, 4]) };
    emails = { queueConfirmations: vi.fn() };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrderConfirmationSweepService,
        { provide: OrdersRepository, useValue: repository },
        { provide: OrderEmailsProducer, useValue: emails },
      ],
    }).compile();

    sweeper = moduleRef.get(OrderConfirmationSweepService);
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('re-queues confirmations for orders paid in the last hour', async () => {
    await expect(sweeper.sweep()).resolves.toBe(2);

    expect(repository.findIdsPaidSince).toHaveBeenCalledWith(
      new Date('2026-01-01T11:00:00Z'),
    );
    expect(emails.queueConfirmations).toHaveBeenCalledWith([3, 4]);
  });
});
