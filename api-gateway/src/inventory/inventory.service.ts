import { ConflictException, Injectable } from '@nestjs/common';
import type { EntityManager } from 'typeorm';
import type { Inventory } from './entities/inventory.entity.js';
import { InventoryMovementReason } from './enums/inventory-movement-reason.enum.js';
import { InventoryRepository } from './inventory.repository.js';
import type {
  StockItem,
  StockMovementContext,
} from './types/inventory.types.js';

@Injectable()
export class InventoryService {
  constructor(private readonly inventoryRepository: InventoryRepository) {}

  async initializeStock(
    manager: EntityManager,
    productId: number,
    quantity: number,
    userId: number,
  ): Promise<void> {
    const inventory = this.inventoryRepository.withManager(manager);

    await inventory.create(productId, quantity);

    if (quantity > 0) {
      await inventory.recordMovements([
        {
          productId,
          userId,
          change: quantity,
          reason: InventoryMovementReason.Initial,
        },
      ]);
    }
  }

  async reserve(
    manager: EntityManager,
    items: StockItem[],
    context: StockMovementContext,
  ): Promise<void> {
    const mergedItems = mergeStockItems(items);

    if (mergedItems.length === 0) {
      return;
    }

    const inventory = this.inventoryRepository.withManager(manager);
    const stock = await inventory.lockByProductIds(
      mergedItems.map((item) => item.productId),
    );

    this.ensureSufficientStock(mergedItems, stock);

    await this.applyChanges(
      inventory,
      mergedItems,
      -1,
      InventoryMovementReason.Sale,
      context,
    );
  }

  async release(
    manager: EntityManager,
    items: StockItem[],
    context: StockMovementContext,
  ): Promise<void> {
    const mergedItems = mergeStockItems(items);

    if (mergedItems.length === 0) {
      return;
    }

    await this.applyChanges(
      this.inventoryRepository.withManager(manager),
      mergedItems,
      1,
      InventoryMovementReason.Cancellation,
      context,
    );
  }

  private ensureSufficientStock(items: StockItem[], stock: Inventory[]): void {
    const available = new Map(
      stock.map((row) => [row.productId, row.quantity]),
    );

    const shortages = items
      .filter((item) => (available.get(item.productId) ?? 0) < item.quantity)
      .map(
        (item) =>
          `Insufficient stock for product ${item.productId}: requested ${item.quantity}, available ${available.get(item.productId) ?? 0}`,
      );

    if (shortages.length > 0) {
      throw new ConflictException(shortages);
    }
  }

  private async applyChanges(
    inventory: InventoryRepository,
    items: StockItem[],
    direction: 1 | -1,
    reason: InventoryMovementReason,
    context: StockMovementContext,
  ): Promise<void> {
    for (const { productId, quantity } of items) {
      await inventory.adjustQuantity(productId, direction * quantity);
    }

    await inventory.recordMovements(
      items.map(({ productId, quantity }) => ({
        productId,
        orderId: context.orderId,
        userId: context.userId,
        change: direction * quantity,
        reason,
      })),
    );
  }
}

function mergeStockItems(items: StockItem[]): StockItem[] {
  const quantities = new Map<number, number>();

  for (const { productId, quantity } of items) {
    quantities.set(productId, (quantities.get(productId) ?? 0) + quantity);
  }

  return [...quantities]
    .map(([productId, quantity]) => ({ productId, quantity }))
    .sort((a, b) => a.productId - b.productId);
}
