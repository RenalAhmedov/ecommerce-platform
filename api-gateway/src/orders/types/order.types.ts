import type { StockItem } from '../../inventory/types/inventory.types.js';
import type { OrderItem } from '../entities/order-item.entity.js';
import type { Order } from '../entities/order.entity.js';

export type NewOrder = Pick<
  Order,
  'orderNumber' | 'userId' | 'status' | 'subtotal' | 'total' | 'shippingAddress'
>;

export type NewOrderItem = Pick<
  OrderItem,
  'productId' | 'productName' | 'unitPrice' | 'quantity' | 'lineTotal'
>;

export interface PlacedOrder {
  id: number;
  orderNumber: string;
  userId: number;
  total: string;
  items: StockItem[];
}
