import { getQueueToken } from '@nestjs/bullmq';
import { Test } from '@nestjs/testing';
import { ORDER_EMAILS_QUEUE } from '../../queues/order-emails.constants.js';
import { OrderEmailsProducer } from '../../queues/order-emails.producer.js';

describe('OrderEmailsProducer', () => {
  let producer: OrderEmailsProducer;
  let queue: {
    on: ReturnType<typeof vi.fn>;
    addBulk: ReturnType<typeof vi.fn>;
  };

  beforeEach(async () => {
    queue = { on: vi.fn(), addBulk: vi.fn() };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrderEmailsProducer,
        { provide: getQueueToken(ORDER_EMAILS_QUEUE), useValue: queue },
      ],
    }).compile();

    producer = moduleRef.get(OrderEmailsProducer);
  });

  it('uses the order id as job id so the same order is never queued twice', async () => {
    await producer.queueConfirmations([3, 4]);

    const [jobs] = queue.addBulk.mock.calls[0] as [
      { name: string; data: unknown; opts: Record<string, unknown> }[],
    ];
    expect(jobs.map((job) => job.opts.jobId)).toEqual([
      'order-confirmation-3',
      'order-confirmation-4',
    ]);
    expect(jobs[0]).toMatchObject({
      name: 'order-confirmation',
      data: { orderId: 3 },
      opts: { attempts: 5, backoff: { type: 'exponential', delay: 5_000 } },
    });
  });

  it('skips Redis entirely for an empty list', async () => {
    await producer.queueConfirmations([]);

    expect(queue.addBulk).not.toHaveBeenCalled();
  });

  it('never fails the caller when Redis is unavailable', async () => {
    queue.addBulk.mockRejectedValue(new Error("Stream isn't writeable"));

    await expect(producer.queueConfirmation(3)).resolves.toBeUndefined();
  });
});
