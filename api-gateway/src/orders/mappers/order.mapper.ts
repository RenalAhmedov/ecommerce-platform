import { fromCents, toCents } from '../../common/utils/money.js';
import type { PurchasableProduct } from '../../products/types/product.types.js';
import type { CreateOrderDto } from '../dto/create-order.dto.js';
import type { OrderItemDto } from '../dto/order-item.dto.js';
import { OrderStatus } from '../enums/order-status.enum.js';
import type { NewOrder, NewOrderItem } from '../types/order.types.js';
import { generateOrderNumber } from '../utils/order-number.js';

export function toOrderLines(
  items: OrderItemDto[],
  products: PurchasableProduct[],
): NewOrderItem[] {
  const productsById = new Map(
    products.map((product) => [product.id, product]),
  );

  return items.map(({ productId, quantity }) => {
    const product = productsById.get(productId);

    if (!product) {
      throw new Error(`Product ${productId} was not loaded`);
    }

    return {
      productId,
      productName: product.name,
      unitPrice: product.price,
      quantity,
      lineTotal: fromCents(toCents(product.price) * quantity),
    };
  });
}

export function sumLineTotals(lines: NewOrderItem[]): number {
  return lines.reduce((sum, line) => sum + toCents(line.lineTotal), 0);
}

export function toNewOrder(
  userId: number,
  dto: CreateOrderDto,
  totalCents: number,
): NewOrder {
  return {
    orderNumber: generateOrderNumber(),
    userId,
    status: OrderStatus.Pending,
    subtotal: fromCents(totalCents),
    total: fromCents(totalCents),
    shippingAddress: dto.shippingAddress,
  };
}
