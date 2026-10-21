import {
  Check,
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  type Relation,
} from 'typeorm';
import { Order } from '../../orders/entities/order.entity.js';
import { Product } from '../../products/entities/product.entity.js';
import { User } from '../../users/entities/user.entity.js';
import { InventoryMovementReason } from '../enums/inventory-movement-reason.enum.js';

@Entity('inventory_movements')
@Check('inventory_movements_change_check', 'change <> 0')
@Check(
  'inventory_movements_reason_check',
  `reason IN ('initial', 'restock', 'sale', 'cancellation', 'adjustment')`,
)
export class InventoryMovement {
  @PrimaryGeneratedColumn('identity', {
    type: 'bigint',
    generatedIdentity: 'ALWAYS',
  })
  id: number;

  @Column({ name: 'product_id', type: 'bigint' })
  productId: number;

  @Column({ name: 'order_id', type: 'bigint', nullable: true })
  orderId: number | null;

  @Column({ name: 'user_id', type: 'bigint', nullable: true })
  userId: number | null;

  @Column({ type: 'integer' })
  change: number;

  @Column({ type: 'varchar', length: 20 })
  reason: InventoryMovementReason;

  @Column({ type: 'varchar', length: 255, nullable: true })
  note: string | null;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;

  @ManyToOne(() => Product, { onDelete: 'CASCADE' })
  @JoinColumn({
    name: 'product_id',
    foreignKeyConstraintName: 'inventory_movements_product_id_foreign',
  })
  product: Relation<Product>;

  @ManyToOne(() => Order, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({
    name: 'order_id',
    foreignKeyConstraintName: 'inventory_movements_order_id_foreign',
  })
  order: Relation<Order> | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({
    name: 'user_id',
    foreignKeyConstraintName: 'inventory_movements_user_id_foreign',
  })
  user: Relation<User> | null;
}
