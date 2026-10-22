import { Controller } from '@nestjs/common';
import { EventPattern, Payload } from '@nestjs/microservices';
import { OrderEmailsProducer } from '../queues/order-emails.producer.js';
import type { OrderPaidEvent } from './interfaces/order-paid-event.interface.js';
import { ORDER_PAID_EVENT } from './order-events.constants.js';

@Controller()
export class OrderEventsController {
  constructor(private readonly orderEmailsProducer: OrderEmailsProducer) {}

  @EventPattern(ORDER_PAID_EVENT)
  onOrderPaid(@Payload() event: OrderPaidEvent): Promise<void> {
    return this.orderEmailsProducer.queueConfirmation(event.orderId);
  }
}
