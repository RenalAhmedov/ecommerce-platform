import type { Order } from '../entities/order.entity.js';
import type { ShippingAddressDto } from './shipping-address.dto.js';
import { OrderItemResponseDto } from './order-item-response.dto.js';
import { OrderSummaryResponseDto } from './order-summary-response.dto.js';

export class OrderResponseDto extends OrderSummaryResponseDto {
  paymentReference: string | null;
  shippingAddress: ShippingAddressDto | null;
  items: OrderItemResponseDto[];

  static override fromEntity(order: Order): OrderResponseDto {
    return Object.assign(OrderSummaryResponseDto.fromEntity(order), {
      paymentReference: order.paymentReference,
      shippingAddress: order.shippingAddress,
      items: order.items.map((item) => OrderItemResponseDto.fromEntity(item)),
    });
  }
}
