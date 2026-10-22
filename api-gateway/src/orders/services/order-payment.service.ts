import { Injectable, Logger } from '@nestjs/common';
import { InventoryService } from '../../inventory/inventory.service.js';
import type { ChargeResult } from '../../payments/interfaces/charge.interface.js';
import { PaymentGateway } from '../../payments/payment.gateway.js';
import { OrderStatus } from '../enums/order-status.enum.js';
import { OrdersRepository } from '../orders.repository.js';
import type { PlacedOrder } from '../types/order.types.js';

@Injectable()
export class OrderPaymentService {
  private readonly logger = new Logger(OrderPaymentService.name);

  constructor(
    private readonly ordersRepository: OrdersRepository,
    private readonly inventoryService: InventoryService,
    private readonly paymentGateway: PaymentGateway,
  ) {}

  async settle(order: PlacedOrder, paymentToken: string): Promise<boolean> {
    const result = await this.charge(order, paymentToken);

    if (result.status === 'succeeded') {
      await this.markPaid(order, result.transactionId);
      return true;
    }

    await this.markFailed(order);
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
      const reason = error instanceof Error ? error.message : String(error);
      this.logger.error(`Payment for ${order.orderNumber} failed: ${reason}`);

      return { status: 'declined', reason: 'gateway_error' };
    }
  }

  private markPaid(order: PlacedOrder, transactionId: string): Promise<void> {
    return this.ordersRepository.transaction(async (orders) => {
      const status = await orders.lockStatusById(order.id);

      if (status !== OrderStatus.Pending) {
        this.logger.warn(
          `Payment ${transactionId} succeeded but order ${order.orderNumber} is ${status}`,
        );
        return;
      }

      await orders.markPaid(order.id, transactionId);
    });
  }

  private markFailed(order: PlacedOrder): Promise<void> {
    return this.ordersRepository.transaction(async (orders, manager) => {
      if ((await orders.lockStatusById(order.id)) !== OrderStatus.Pending) {
        return;
      }

      await orders.updateStatus(order.id, OrderStatus.PaymentFailed);
      await this.inventoryService.release(manager, order.items, {
        orderId: order.id,
        userId: order.userId,
      });
    });
  }
}
