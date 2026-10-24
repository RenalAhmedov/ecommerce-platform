import { Module } from '@nestjs/common';
import { BullModule } from '@nestjs/bullmq';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CategoriesModule } from '../categories/categories.module.js';
import { InventoryModule } from '../inventory/inventory.module.js';
import { Product } from './entities/product.entity.js';
import { ProductChangeNotifier } from './product-change.notifier.js';
import { ProductsCache } from './products.cache.js';
import { ProductsController } from './products.controller.js';
import { ProductsRepository } from './products.repository.js';
import { ProductsResolver } from './products.resolver.js';
import { ProductSearchProcessor } from './queues/product-search.processor.js';
import { ProductSearchProducer } from './queues/product-search.producer.js';
import { PRODUCT_SEARCH_QUEUE } from './search/product-search.constants.js';
import { ProductSearchRepository } from './search/product-search.repository.js';
import { ProductSearchService } from './search/product-search.service.js';
import { ProductSearchSyncService } from './search/product-search-sync.service.js';
import { ProductCatalogService } from './services/product-catalog.service.js';
import { ProductsReadService } from './services/products-read.service.js';
import { ProductsWriteService } from './services/products-write.service.js';
import { ProductValidator } from './validators/product.validator.js';

@Module({
  imports: [
    TypeOrmModule.forFeature([Product]),
    BullModule.registerQueue({ name: PRODUCT_SEARCH_QUEUE }),
    CategoriesModule,
    InventoryModule,
  ],
  controllers: [ProductsController],
  providers: [
    ProductsRepository,
    ProductsResolver,
    ProductsCache,
    ProductValidator,
    ProductsReadService,
    ProductsWriteService,
    ProductCatalogService,
    ProductChangeNotifier,
    ProductSearchRepository,
    ProductSearchService,
    ProductSearchSyncService,
    ProductSearchProducer,
    ProductSearchProcessor,
  ],
  exports: [ProductCatalogService, ProductsCache, ProductChangeNotifier],
})
export class ProductsModule {}
