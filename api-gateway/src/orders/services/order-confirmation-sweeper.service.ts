import { Injectable } from '@nestjs/common';
import { CONFIRMATION_SWEEP_WINDOW_MINUTES } from '../constants/order-maintenance.constants.js';
import { OrdersRepository } from '../orders.repository.js';
import { OrderEmailsProducer } from '../queues/order-emails.producer.js';

@Injectable()
export class OrderConfirmationSweeper {
  constructor(
    private readonly ordersRepository: OrdersRepository,
    private readonly orderEmailsProducer: OrderEmailsProducer,
  ) {}

  async sweep(): Promise<number> {
    const paidSince = new Date(
      Date.now() - CONFIRMATION_SWEEP_WINDOW_MINUTES * 60_000,
    );
    const orderIds = await this.ordersRepository.findIdsPaidSince(paidSince);

    await this.orderEmailsProducer.queueConfirmations(orderIds);

    return orderIds.length;
  }
}
