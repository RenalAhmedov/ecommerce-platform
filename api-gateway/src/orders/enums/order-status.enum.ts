import { registerEnumType } from '@nestjs/graphql';

export enum OrderStatus {
  Pending = 'pending',
  Paid = 'paid',
  Processing = 'processing',
  Shipped = 'shipped',
  Delivered = 'delivered',
  Cancelled = 'cancelled',
  PaymentFailed = 'payment_failed',
}

registerEnumType(OrderStatus, { name: 'OrderStatus' });
