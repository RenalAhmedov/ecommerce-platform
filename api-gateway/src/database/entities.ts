import { Category } from '../categories/entities/category.entity.js';
import { InventoryMovement } from '../inventory/entities/inventory-movement.entity.js';
import { Inventory } from '../inventory/entities/inventory.entity.js';
import { OrderItem } from '../orders/entities/order-item.entity.js';
import { Order } from '../orders/entities/order.entity.js';
import { Product } from '../products/entities/product.entity.js';
import { User } from '../users/entities/user.entity.js';

export const ENTITIES = [
  User,
  Category,
  Product,
  Inventory,
  InventoryMovement,
  Order,
  OrderItem,
];
