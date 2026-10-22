import type { Order } from '../entities/order.entity.js';
import type { ShippingAddress } from '../interfaces/shipping-address.interface.js';
import { OrderItemResponseDto } from './order-item-response.dto.js';
import { OrderSummaryResponseDto } from './order-summary-response.dto.js';

export class OrderResponseDto extends OrderSummaryResponseDto {
  paymentReference: string | null;
  shippingAddress: ShippingAddress | null;
  items: OrderItemResponseDto[];

  static override fromEntity(order: Order): OrderResponseDto {
    return Object.assign(OrderSummaryResponseDto.fromEntity(order), {
      paymentReference: order.paymentReference,
      shippingAddress: order.shippingAddress,
      items: order.items.map((item) => OrderItemResponseDto.fromEntity(item)),
    });
  }
}
