import {
  Check,
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  type Relation,
  Unique,
  UpdateDateColumn,
} from 'typeorm';
import { Product } from '../../products/entities/product.entity.js';
import { Order } from './order.entity.js';

@Entity('order_items')
@Unique('order_items_order_id_product_id_unique', ['orderId', 'productId'])
@Check('order_items_unit_price_check', 'unit_price >= 0')
@Check('order_items_quantity_check', 'quantity > 0')
@Check('order_items_line_total_check', 'line_total >= 0')
export class OrderItem {
  @PrimaryGeneratedColumn('identity', {
    type: 'bigint',
    generatedIdentity: 'ALWAYS',
  })
  id: number;

  @Column({ name: 'order_id', type: 'bigint' })
  orderId: number;

  @Column({ name: 'product_id', type: 'bigint' })
  productId: number;

  @Column({ name: 'product_name', type: 'varchar', length: 255 })
  productName: string;

  @Column({ name: 'unit_price', type: 'numeric', precision: 12, scale: 2 })
  unitPrice: string;

  @Column({ type: 'integer' })
  quantity: number;

  @Column({ name: 'line_total', type: 'numeric', precision: 12, scale: 2 })
  lineTotal: string;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at', type: 'timestamptz' })
  updatedAt: Date;

  @ManyToOne(() => Order, (order) => order.items, { onDelete: 'CASCADE' })
  @JoinColumn({
    name: 'order_id',
    foreignKeyConstraintName: 'order_items_order_id_foreign',
  })
  order: Relation<Order>;

  @ManyToOne(() => Product, { onDelete: 'RESTRICT' })
  @JoinColumn({
    name: 'product_id',
    foreignKeyConstraintName: 'order_items_product_id_foreign',
  })
  product: Relation<Product>;
}
