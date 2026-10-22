import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { type EntityManager, Repository } from 'typeorm';
import { InventoryMovement } from './entities/inventory-movement.entity.js';
import { Inventory } from './entities/inventory.entity.js';
import type { NewInventoryMovement } from './types/inventory.types.js';

@Injectable()
export class InventoryRepository {
  constructor(
    @InjectRepository(Inventory)
    private readonly inventories: Repository<Inventory>,
    @InjectRepository(InventoryMovement)
    private readonly movements: Repository<InventoryMovement>,
  ) {}

  withManager(manager: EntityManager): InventoryRepository {
    return new InventoryRepository(
      manager.getRepository(Inventory),
      manager.getRepository(InventoryMovement),
    );
  }

  async create(productId: number, quantity: number): Promise<void> {
    await this.inventories.insert({ productId, quantity });
  }

  lockByProductIds(productIds: number[]): Promise<Inventory[]> {
    return this.inventories
      .createQueryBuilder('inventory')
      .select(['inventory.id', 'inventory.productId', 'inventory.quantity'])
      .where('inventory.productId IN (:...productIds)', { productIds })
      .orderBy('inventory.productId', 'ASC')
      .setLock('pessimistic_write')
      .getMany();
  }

  async adjustQuantity(productId: number, change: number): Promise<void> {
    await this.inventories
      .createQueryBuilder()
      .update()
      .set({ quantity: () => 'quantity + :change' })
      .where('product_id = :productId')
      .setParameters({ change, productId })
      .execute();
  }

  async recordMovements(movements: NewInventoryMovement[]): Promise<void> {
    if (movements.length === 0) {
      return;
    }

    await this.movements.insert(movements);
  }
}
