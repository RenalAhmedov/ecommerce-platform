import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CategoriesModule } from '../categories/categories.module.js';
import { Product } from './entities/product.entity.js';
import { ProductsCache } from './products.cache.js';
import { ProductsController } from './products.controller.js';
import { ProductsRepository } from './products.repository.js';
import { ProductsReadService } from './services/products-read.service.js';
import { ProductsWriteService } from './services/products-write.service.js';
import { ProductValidator } from './validators/product.validator.js';

@Module({
  imports: [TypeOrmModule.forFeature([Product]), CategoriesModule],
  controllers: [ProductsController],
  providers: [
    ProductsRepository,
    ProductsCache,
    ProductValidator,
    ProductsReadService,
    ProductsWriteService,
  ],
})
export class ProductsModule {}
