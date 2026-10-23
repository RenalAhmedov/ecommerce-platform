import { Injectable, NotFoundException } from '@nestjs/common';
import { InventoryService } from '../../inventory/inventory.service.js';
import type { CreateProductDto } from '../dto/create-product.dto.js';
import { ProductResponseDto } from '../dto/product-response.dto.js';
import type { UpdateProductDto } from '../dto/update-product.dto.js';
import { toNewProduct, toProductChanges } from '../mappers/product.mapper.js';
import { ProductsCache } from '../products.cache.js';
import { ProductsRepository } from '../products.repository.js';
import { ProductValidator } from '../validators/product.validator.js';

@Injectable()
export class ProductsWriteService {
  constructor(
    private readonly productsRepository: ProductsRepository,
    private readonly productsCache: ProductsCache,
    private readonly productValidator: ProductValidator,
    private readonly inventoryService: InventoryService,
  ) {}

  async create(
    dto: CreateProductDto,
    createdBy: number,
  ): Promise<ProductResponseDto> {
    const slug = this.productValidator.resolveSlug(dto);
    await this.productValidator.ensureCategoryExists(dto.categoryId);

    const product = await this.productsRepository.transaction(
      async (products, manager) => {
        await products.lockUniqueValues({ sku: dto.sku, slug });
        await this.productValidator.ensureSkuAvailable(products, dto.sku);
        await this.productValidator.ensureSlugAvailable(products, slug);

        const { id } = await products.create(toNewProduct(dto, slug));
        await this.inventoryService.initializeStock(
          manager,
          id,
          dto.initialStock ?? 0,
          createdBy,
        );

        return products.getById(id);
      },
    );

    await this.productsCache.invalidate();

    return ProductResponseDto.fromEntity(product);
  }

  async update(id: number, dto: UpdateProductDto): Promise<ProductResponseDto> {
    const changes = toProductChanges(dto);
    this.productValidator.ensureHasChanges(changes);

    const product = await this.productsRepository.transaction(
      async (products) => {
        if (!(await products.lockById(id))) {
          throw new NotFoundException('Product not found');
        }

        await this.productValidator.ensureCategoryExists(changes.categoryId);
        await products.lockUniqueValues({
          sku: changes.sku,
          slug: changes.slug,
        });

        if (changes.sku !== undefined) {
          await this.productValidator.ensureSkuAvailable(
            products,
            changes.sku,
            id,
          );
        }

        if (changes.slug !== undefined) {
          await this.productValidator.ensureSlugAvailable(
            products,
            changes.slug,
            id,
          );
        }

        await products.update(id, changes);

        return products.getById(id);
      },
    );

    await this.productsCache.invalidate();

    return ProductResponseDto.fromEntity(product);
  }
}
