import type { OrderItem } from '../entities/order-item.entity.js';

export class OrderItemResponseDto {
  id: number;
  productId: number;
  productName: string;
  unitPrice: string;
  quantity: number;
  lineTotal: string;

  static fromEntity(item: OrderItem): OrderItemResponseDto {
    return {
      id: item.id,
      productId: item.productId,
      productName: item.productName,
      unitPrice: item.unitPrice,
      quantity: item.quantity,
      lineTotal: item.lineTotal,
    };
  }
}
