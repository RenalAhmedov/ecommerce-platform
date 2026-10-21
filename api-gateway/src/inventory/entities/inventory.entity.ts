import {
  Check,
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  OneToOne,
  PrimaryGeneratedColumn,
  type Relation,
  UpdateDateColumn,
} from 'typeorm';
import { Product } from '../../products/entities/product.entity.js';

@Entity('inventories')
@Check('inventories_quantity_check', 'quantity >= 0')
@Check('inventories_low_stock_threshold_check', 'low_stock_threshold >= 0')
export class Inventory {
  @PrimaryGeneratedColumn('identity', {
    type: 'bigint',
    generatedIdentity: 'ALWAYS',
  })
  id: number;

  @Column({ name: 'product_id', type: 'bigint', unique: true })
  productId: number;

  @Column({ type: 'integer', default: 0 })
  quantity: number;

  @Column({ name: 'low_stock_threshold', type: 'integer', default: 5 })
  lowStockThreshold: number;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at', type: 'timestamptz' })
  updatedAt: Date;

  @OneToOne(() => Product, (product) => product.inventory, {
    onDelete: 'CASCADE',
  })
  @JoinColumn({
    name: 'product_id',
    foreignKeyConstraintName: 'inventories_product_id_foreign',
  })
  product: Relation<Product>;
}
