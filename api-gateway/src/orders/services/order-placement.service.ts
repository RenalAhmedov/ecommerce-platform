import { Injectable, Logger } from '@nestjs/common';
import { InventoryService } from '../../inventory/inventory.service.js';
import { ProductCatalogService } from '../../products/services/product-catalog.service.js';
import type { CreateOrderDto } from '../dto/create-order.dto.js';
import {
  sumLineTotals,
  toNewOrder,
  toOrderLines,
} from '../mappers/order.mapper.js';
import { OrdersRepository } from '../orders.repository.js';
import type { PlacedOrder } from '../types/order.types.js';
import { OrderValidator } from '../validators/order.validator.js';

@Injectable()
export class OrderPlacementService {
  private readonly logger = new Logger(OrderPlacementService.name);

  constructor(
    private readonly ordersRepository: OrdersRepository,
    private readonly productCatalogService: ProductCatalogService,
    private readonly inventoryService: InventoryService,
    private readonly orderValidator: OrderValidator,
  ) {}

  async place(userId: number, dto: CreateOrderDto): Promise<PlacedOrder> {
    const order = await this.ordersRepository.transaction(
      async (orders, manager) => {
        const products = await this.productCatalogService.findPurchasable(
          manager,
          dto.items.map((item) => item.productId),
        );
        this.orderValidator.ensureProductsPurchasable(dto.items, products);

        const lines = toOrderLines(dto.items, products);
        const totalCents = sumLineTotals(lines);
        this.orderValidator.ensureWithinTotalLimit(totalCents);

        const order = await orders.createWithItems(
          toNewOrder(userId, dto, totalCents),
          lines,
        );
        await this.inventoryService.reserve(manager, dto.items, {
          orderId: order.id,
          userId,
        });

        return {
          id: order.id,
          orderNumber: order.orderNumber,
          userId,
          total: order.total,
          items: dto.items,
        };
      },
    );

    this.logger.log(
      `Order ${order.orderNumber} placed by user ${userId}: ${order.items.length} product(s), total ${order.total}`,
    );

    return order;
  }
}
