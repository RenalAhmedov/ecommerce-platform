import { Injectable, Logger } from '@nestjs/common';
import { describeError } from '../../common/utils/describe-error.js';
import type { ChargeResult } from '../../payments/interfaces/charge.interface.js';
import { PaymentGateway } from '../../payments/payment.gateway.js';
import { OrderStatus } from '../enums/order-status.enum.js';
import { OrderEventsPublisher } from '../events/order-events.publisher.js';
import { OrdersRepository } from '../orders.repository.js';
import type { PlacedOrder } from '../types/order.types.js';
import { OrderReleaseService } from './order-release.service.js';

@Injectable()
export class OrderPaymentService {
  private readonly logger = new Logger(OrderPaymentService.name);

  constructor(
    private readonly ordersRepository: OrdersRepository,
    private readonly paymentGateway: PaymentGateway,
    private readonly orderEventsPublisher: OrderEventsPublisher,
    private readonly orderReleaseService: OrderReleaseService,
  ) {}

  async settle(order: PlacedOrder, paymentToken: string): Promise<boolean> {
    const result = await this.charge(order, paymentToken);

    if (result.status === 'succeeded') {
      if (await this.markPaid(order, result.transactionId)) {
        await this.orderEventsPublisher.publishPaid({
          orderId: order.id,
          orderNumber: order.orderNumber,
          userId: order.userId,
          total: order.total,
          paymentReference: result.transactionId,
        });
      }
      return true;
    }

    await this.orderReleaseService.releasePending(order);
    return false;
  }

  private async charge(
    order: PlacedOrder,
    token: string,
  ): Promise<ChargeResult> {
    try {
      return await this.paymentGateway.charge({
        reference: order.orderNumber,
        amount: order.total,
        token,
      });
    } catch (error) {
      this.logger.error(
        `Payment for ${order.orderNumber} failed: ${describeError(error)}`,
      );

      return { status: 'declined', reason: 'gateway_error' };
    }
  }

  private markPaid(
    order: PlacedOrder,
    transactionId: string,
  ): Promise<boolean> {
    return this.ordersRepository.transaction(async (orders) => {
      const status = await orders.lockStatusById(order.id);

      if (status !== OrderStatus.Pending) {
        this.logger.warn(
          `Payment ${transactionId} succeeded but order ${order.orderNumber} is ${status}`,
        );
        return false;
      }

      await orders.markPaid(order.id, transactionId);
      return true;
    });
  }
}
