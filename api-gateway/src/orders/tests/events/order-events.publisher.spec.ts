import { Test } from '@nestjs/testing';
import { EMPTY, NEVER, of, throwError } from 'rxjs';
import {
  ORDER_EVENTS_CLIENT,
  ORDER_PAID_EVENT,
} from '../../events/order-events.constants.js';
import { OrderEventsPublisher } from '../../events/order-events.publisher.js';

describe('OrderEventsPublisher', () => {
  let publisher: OrderEventsPublisher;
  let client: { emit: ReturnType<typeof vi.fn> };

  const event = {
    orderId: 10,
    orderNumber: 'ORD-1',
    userId: 9,
    total: '45.00',
    paymentReference: 'mock_1',
  };

  beforeEach(async () => {
    client = { emit: vi.fn().mockReturnValue(EMPTY) };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrderEventsPublisher,
        { provide: ORDER_EVENTS_CLIENT, useValue: client },
      ],
    }).compile();

    publisher = moduleRef.get(OrderEventsPublisher);
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('emits order.paid with the event payload', async () => {
    client.emit.mockReturnValue(of(undefined));

    await publisher.publishPaid(event);

    expect(client.emit).toHaveBeenCalledWith(ORDER_PAID_EVENT, event);
  });

  it('never fails the order when publishing fails', async () => {
    client.emit.mockReturnValue(throwError(() => new Error('ECONNREFUSED')));

    await expect(publisher.publishPaid(event)).resolves.toBeUndefined();
  });

  it('gives up after the timeout instead of hanging the request', async () => {
    vi.useFakeTimers();
    client.emit.mockReturnValue(NEVER);

    const publishing = publisher.publishPaid(event);
    await vi.advanceTimersByTimeAsync(1_000);

    await expect(publishing).resolves.toBeUndefined();
  });
});
