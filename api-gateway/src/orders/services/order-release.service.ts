import { Injectable } from '@nestjs/common';
import { InventoryService } from '../../inventory/inventory.service.js';
import { OrderStatus } from '../enums/order-status.enum.js';
import { OrdersRepository } from '../orders.repository.js';
import type { PlacedOrder } from '../types/order.types.js';

@Injectable()
export class OrderReleaseService {
  constructor(
    private readonly ordersRepository: OrdersRepository,
    private readonly inventoryService: InventoryService,
  ) {}

  releasePending(order: PlacedOrder): Promise<boolean> {
    return this.ordersRepository.transaction(async (orders, manager) => {
      if ((await orders.lockStatusById(order.id)) !== OrderStatus.Pending) {
        return false;
      }

      await orders.updateStatus(order.id, OrderStatus.PaymentFailed);
      await this.inventoryService.release(manager, order.items, {
        orderId: order.id,
        userId: order.userId,
      });

      return true;
    });
  }
}
