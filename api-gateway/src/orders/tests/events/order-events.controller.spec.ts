import { Test } from '@nestjs/testing';
import { OrderEventsController } from '../../events/order-events.controller.js';
import { OrderEmailsProducer } from '../../queues/order-emails.producer.js';

describe('OrderEventsController', () => {
  it('queues the confirmation email when an order is paid', async () => {
    const emails = { queueConfirmation: vi.fn() };
    const moduleRef = await Test.createTestingModule({
      controllers: [OrderEventsController],
      providers: [{ provide: OrderEmailsProducer, useValue: emails }],
    }).compile();

    await moduleRef.get(OrderEventsController).onOrderPaid({
      orderId: 10,
      orderNumber: 'ORD-1',
      userId: 9,
      total: '45.00',
      paymentReference: 'mock_1',
    });

    expect(emails.queueConfirmation).toHaveBeenCalledWith(10);
  });
});
