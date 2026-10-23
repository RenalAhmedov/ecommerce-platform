import { BadRequestException, Injectable } from '@nestjs/common';
import type { PurchasableProduct } from '../../products/types/product.types.js';
import { MAX_ORDER_TOTAL_CENTS } from '../constants/order.constants.js';
import type { OrderItemDto } from '../dto/order-item.dto.js';

@Injectable()
export class OrderValidator {
  ensureProductsPurchasable(
    items: OrderItemDto[],
    products: PurchasableProduct[],
  ): void {
    const availableIds = new Set(products.map((product) => product.id));
    const unavailableIds = items
      .map((item) => item.productId)
      .filter((productId) => !availableIds.has(productId));

    if (unavailableIds.length > 0) {
      throw new BadRequestException(
        `Products not available: ${unavailableIds.join(', ')}`,
      );
    }
  }

  ensureWithinTotalLimit(totalCents: number): void {
    if (totalCents > MAX_ORDER_TOTAL_CENTS) {
      throw new BadRequestException(
        'Order total exceeds the maximum allowed amount',
      );
    }
  }
}
