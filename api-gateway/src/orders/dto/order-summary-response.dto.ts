import type { Order } from '../entities/order.entity.js';
import type { OrderStatus } from '../enums/order-status.enum.js';

export class OrderSummaryResponseDto {
  id: number;
  orderNumber: string;
  status: OrderStatus;
  subtotal: string;
  total: string;
  paidAt: Date | null;
  createdAt: Date;
  updatedAt: Date;

  static fromEntity(order: Order): OrderSummaryResponseDto {
    return {
      id: order.id,
      orderNumber: order.orderNumber,
      status: order.status,
      subtotal: order.subtotal,
      total: order.total,
      paidAt: order.paidAt,
      createdAt: order.createdAt,
      updatedAt: order.updatedAt,
    };
  }
}
