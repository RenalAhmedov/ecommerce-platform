import { Field, Int, ObjectType } from '@nestjs/graphql';
import type { OrderItem } from '../entities/order-item.entity.js';

@ObjectType('OrderItem')
export class OrderItemResponseDto {
  @Field(() => Int)
  id: number;

  @Field(() => Int)
  productId: number;

  @Field()
  productName: string;

  @Field()
  unitPrice: string;

  @Field(() => Int)
  quantity: number;

  @Field()
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
