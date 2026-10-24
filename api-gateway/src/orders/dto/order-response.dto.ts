import { Field, ObjectType } from '@nestjs/graphql';
import type { Order } from '../entities/order.entity.js';
import { OrderItemResponseDto } from './order-item-response.dto.js';
import { OrderSummaryResponseDto } from './order-summary-response.dto.js';
import { ShippingAddressResponseDto } from './shipping-address-response.dto.js';

@ObjectType('Order')
export class OrderResponseDto extends OrderSummaryResponseDto {
  @Field(() => String, { nullable: true })
  paymentReference: string | null;

  @Field(() => ShippingAddressResponseDto, { nullable: true })
  shippingAddress: ShippingAddressResponseDto | null;

  @Field(() => [OrderItemResponseDto])
  items: OrderItemResponseDto[];

  static override fromEntity(order: Order): OrderResponseDto {
    return Object.assign(OrderSummaryResponseDto.fromEntity(order), {
      paymentReference: order.paymentReference,
      shippingAddress: order.shippingAddress,
      items: order.items.map((item) => OrderItemResponseDto.fromEntity(item)),
    });
  }
}
