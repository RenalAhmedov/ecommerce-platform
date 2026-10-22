import {
  Check,
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
  type Relation,
  Unique,
  UpdateDateColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity.js';
import { OrderStatus } from '../enums/order-status.enum.js';
import type { ShippingAddress } from '../interfaces/shipping-address.interface.js';
import { OrderItem } from './order-item.entity.js';

@Entity('orders')
@Unique('orders_order_number_unique', ['orderNumber'])
@Check(
  'orders_status_check',
  `status IN ('pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'payment_failed')`,
)
@Check('orders_subtotal_check', 'subtotal >= 0')
@Check('orders_total_check', 'total >= 0')
export class Order {
  @PrimaryGeneratedColumn('identity', {
    type: 'bigint',
    generatedIdentity: 'ALWAYS',
  })
  id: number;

  @Column({ name: 'order_number', type: 'varchar', length: 32 })
  orderNumber: string;

  @Column({ name: 'user_id', type: 'bigint' })
  userId: number;

  @Column({ type: 'varchar', length: 20, default: OrderStatus.Pending })
  status: OrderStatus;

  @Column({ type: 'numeric', precision: 12, scale: 2 })
  subtotal: string;

  @Column({ type: 'numeric', precision: 12, scale: 2 })
  total: string;

  @Column({
    name: 'payment_reference',
    type: 'varchar',
    length: 100,
    nullable: true,
  })
  paymentReference: string | null;

  @Column({ name: 'shipping_address', type: 'jsonb', nullable: true })
  shippingAddress: ShippingAddress | null;

  @Column({ name: 'paid_at', type: 'timestamptz', nullable: true })
  paidAt: Date | null;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at', type: 'timestamptz' })
  updatedAt: Date;

  @ManyToOne(() => User, (user) => user.orders, { onDelete: 'RESTRICT' })
  @JoinColumn({
    name: 'user_id',
    foreignKeyConstraintName: 'orders_user_id_foreign',
  })
  user: Relation<User>;

  @OneToMany(() => OrderItem, (item) => item.order)
  items: Relation<OrderItem[]>;
}
