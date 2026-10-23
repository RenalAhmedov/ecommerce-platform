import { Injectable, Logger } from '@nestjs/common';
import { ProductsCache } from '../../products/products.cache.js';
import {
  STALE_ORDER_BATCH_SIZE,
  STALE_PENDING_ORDER_MINUTES,
} from '../constants/order-maintenance.constants.js';
import { toPlacedOrder } from '../mappers/order.mapper.js';
import { OrdersRepository } from '../orders.repository.js';
import { OrderReleaseService } from './order-release.service.js';

@Injectable()
export class OrderExpiryService {
  private readonly logger = new Logger(OrderExpiryService.name);

  constructor(
    private readonly ordersRepository: OrdersRepository,
    private readonly orderReleaseService: OrderReleaseService,
    private readonly productsCache: ProductsCache,
  ) {}

  async expireStaleOrders(): Promise<number> {
    const createdBefore = new Date(
      Date.now() - STALE_PENDING_ORDER_MINUTES * 60_000,
    );
    const staleOrders = await this.ordersRepository.findStalePending(
      createdBefore,
      STALE_ORDER_BATCH_SIZE,
    );

    let expired = 0;
    for (const order of staleOrders) {
      if (await this.orderReleaseService.releasePending(toPlacedOrder(order))) {
        expired++;
      }
    }

    if (expired > 0) {
      await this.productsCache.invalidate();
      this.logger.log(`Expired ${expired} stale pending order(s)`);
    }

    return expired;
  }
}
