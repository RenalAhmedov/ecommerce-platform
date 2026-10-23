import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { InventoryMovement } from './entities/inventory-movement.entity.js';
import { Inventory } from './entities/inventory.entity.js';
import { InventoryRepository } from './inventory.repository.js';
import { InventoryService } from './inventory.service.js';

@Module({
  imports: [TypeOrmModule.forFeature([Inventory, InventoryMovement])],
  providers: [InventoryRepository, InventoryService],
  exports: [InventoryService],
})
export class InventoryModule {}
