import {
  Check,
  Column,
  CreateDateColumn,
  DeleteDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
  type Relation,
  UpdateDateColumn,
} from 'typeorm';
import { Product } from '../../products/entities/product.entity.js';

@Entity('categories')
@Check('categories_parent_check', 'parent_id IS NULL OR parent_id <> id')
export class Category {
  @PrimaryGeneratedColumn('identity', {
    type: 'bigint',
    generatedIdentity: 'ALWAYS',
  })
  id: number;

  @Column({ name: 'parent_id', type: 'bigint', nullable: true })
  parentId: number | null;

  @Column({ type: 'varchar', length: 255 })
  name: string;

  @Column({ type: 'varchar', length: 255 })
  slug: string;

  @Column({ type: 'text', nullable: true })
  description: string | null;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at', type: 'timestamptz' })
  updatedAt: Date;

  @DeleteDateColumn({ name: 'deleted_at', type: 'timestamptz' })
  deletedAt: Date | null;

  @ManyToOne(() => Category, (category) => category.children, {
    nullable: true,
    onDelete: 'SET NULL',
  })
  @JoinColumn({
    name: 'parent_id',
    foreignKeyConstraintName: 'categories_parent_id_foreign',
  })
  parent: Relation<Category> | null;

  @OneToMany(() => Category, (category) => category.parent)
  children: Relation<Category[]>;

  @OneToMany(() => Product, (product) => product.category)
  products: Relation<Product[]>;
}
