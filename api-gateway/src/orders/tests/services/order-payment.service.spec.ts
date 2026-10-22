import { Test } from '@nestjs/testing';
import { PaymentGateway } from '../../../payments/payment.gateway.js';
import { OrderStatus } from '../../enums/order-status.enum.js';
import { OrdersRepository } from '../../orders.repository.js';
import { OrderEventsPublisher } from '../../events/order-events.publisher.js';
import { OrderPaymentService } from '../../services/order-payment.service.js';
import { OrderReleaseService } from '../../services/order-release.service.js';
import type { PlacedOrder } from '../../types/order.types.js';

describe('OrderPaymentService', () => {
  let service: OrderPaymentService;
  let transactionOrders: {
    lockStatusById: ReturnType<typeof vi.fn>;
    markPaid: ReturnType<typeof vi.fn>;
  };
  let ordersRepository: { transaction: ReturnType<typeof vi.fn> };
  let gateway: { charge: ReturnType<typeof vi.fn> };
  let events: { publishPaid: ReturnType<typeof vi.fn> };
  let release: { releasePending: ReturnType<typeof vi.fn> };

  const order: PlacedOrder = {
    id: 10,
    orderNumber: 'ORD-1',
    userId: 9,
    total: '45.00',
    items: [{ productId: 1, quantity: 2 }],
  };

  beforeEach(async () => {
    transactionOrders = {
      lockStatusById: vi.fn().mockResolvedValue(OrderStatus.Pending),
      markPaid: vi.fn(),
    };
    ordersRepository = {
      transaction: vi.fn((work: (orders: unknown) => Promise<unknown>) =>
        work(transactionOrders),
      ),
    };
    gateway = {
      charge: vi
        .fn()
        .mockResolvedValue({ status: 'succeeded', transactionId: 'mock_1' }),
    };
    events = { publishPaid: vi.fn() };
    release = { releasePending: vi.fn().mockResolvedValue(true) };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrderPaymentService,
        { provide: OrdersRepository, useValue: ordersRepository },
        { provide: PaymentGateway, useValue: gateway },
        { provide: OrderEventsPublisher, useValue: events },
        { provide: OrderReleaseService, useValue: release },
      ],
    }).compile();

    service = moduleRef.get(OrderPaymentService);
  });

  it('charges the order total with the order number as reference', async () => {
    await service.settle(order, 'tok_visa');

    expect(gateway.charge).toHaveBeenCalledWith({
      reference: 'ORD-1',
      amount: '45.00',
      token: 'tok_visa',
    });
  });

  it('marks a successful payment as paid and publishes order.paid', async () => {
    await expect(service.settle(order, 'tok_visa')).resolves.toBe(true);

    expect(transactionOrders.markPaid).toHaveBeenCalledWith(10, 'mock_1');
    expect(events.publishPaid).toHaveBeenCalledWith({
      orderId: 10,
      orderNumber: 'ORD-1',
      userId: 9,
      total: '45.00',
      paymentReference: 'mock_1',
    });
    expect(release.releasePending).not.toHaveBeenCalled();
  });

  it('does not touch an order that is no longer pending, and publishes nothing', async () => {
    transactionOrders.lockStatusById.mockResolvedValue(
      OrderStatus.PaymentFailed,
    );

    await expect(service.settle(order, 'tok_visa')).resolves.toBe(true);

    expect(transactionOrders.markPaid).not.toHaveBeenCalled();
    expect(events.publishPaid).not.toHaveBeenCalled();
  });

  it('releases the order when the payment is declined', async () => {
    gateway.charge.mockResolvedValue({
      status: 'declined',
      reason: 'card_declined',
    });

    await expect(service.settle(order, 'tok_declined')).resolves.toBe(false);

    expect(release.releasePending).toHaveBeenCalledWith(order);
    expect(transactionOrders.markPaid).not.toHaveBeenCalled();
    expect(events.publishPaid).not.toHaveBeenCalled();
  });

  it('treats a gateway error as a decline', async () => {
    gateway.charge.mockRejectedValue(new Error('timeout'));

    await expect(service.settle(order, 'tok_visa')).resolves.toBe(false);

    expect(release.releasePending).toHaveBeenCalledWith(order);
  });
});
