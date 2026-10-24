import { Field, Int, ObjectType } from '@nestjs/graphql';
import type { Order } from '../entities/order.entity.js';
import { OrderStatus } from '../enums/order-status.enum.js';

@ObjectType('OrderSummary')
export class OrderSummaryResponseDto {
  @Field(() => Int)
  id: number;

  @Field()
  orderNumber: string;

  @Field(() => OrderStatus)
  status: OrderStatus;

  @Field()
  subtotal: string;

  @Field()
  total: string;

  @Field(() => Date, { nullable: true })
  paidAt: Date | null;

  @Field()
  createdAt: Date;

  @Field()
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
