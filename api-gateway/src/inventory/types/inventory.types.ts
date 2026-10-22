import type { InventoryMovement } from '../entities/inventory-movement.entity.js';

export interface StockItem {
  productId: number;
  quantity: number;
}

export interface StockMovementContext {
  orderId: number;
  userId: number;
}

export type NewInventoryMovement = Pick<
  InventoryMovement,
  'productId' | 'change' | 'reason'
> &
  Partial<Pick<InventoryMovement, 'orderId' | 'userId' | 'note'>>;
