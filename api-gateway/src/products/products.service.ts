import {
  BadRequestException,
  ConflictException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { CategoriesService } from '../categories/categories.service.js';
import type { Paginated } from '../common/interfaces/paginated.interface.js';
import { slugify } from '../common/utils/slugify.js';
import type { CreateProductDto } from './dto/create-product.dto.js';
import type { ProductQueryDto } from './dto/product-query.dto.js';
import { ProductResponseDto } from './dto/product-response.dto.js';
import type { UpdateProductDto } from './dto/update-product.dto.js';
import { ProductsRepository } from './products.repository.js';
import type { ProductChanges } from './types/product.types.js';

@Injectable()
export class ProductsService {
  constructor(
    private readonly productsRepository: ProductsRepository,
    private readonly categoriesService: CategoriesService,
  ) {}

  async findAll(
    filters: ProductQueryDto,
  ): Promise<Paginated<ProductResponseDto>> {
    if (
      filters.minPrice !== undefined &&
      filters.maxPrice !== undefined &&
      filters.minPrice > filters.maxPrice
    ) {
      throw new BadRequestException(
        'minPrice must not be greater than maxPrice',
      );
    }

    const [products, total] =
      await this.productsRepository.findActivePaginated(filters);

    return {
      data: products.map((product) => ProductResponseDto.fromEntity(product)),
      meta: {
        page: filters.page,
        limit: filters.limit,
        total,
        totalPages: Math.ceil(total / filters.limit),
      },
    };
  }

  async findOne(id: number): Promise<ProductResponseDto> {
    const product = await this.productsRepository.findActiveById(id);

    if (!product) {
      throw new NotFoundException('Product not found');
    }

    return ProductResponseDto.fromEntity(product);
  }

  async create(
    dto: CreateProductDto,
    createdBy: number,
  ): Promise<ProductResponseDto> {
    const slug = dto.slug ?? slugify(dto.name);

    if (!slug) {
      throw new BadRequestException(
        'Could not generate a slug from the name, please provide one',
      );
    }

    await this.ensureCategoryExists(dto.categoryId);

    const product = await this.productsRepository.transaction(
      async (products) => {
        await products.lockUniqueValues({ sku: dto.sku, slug });
        await this.ensureSkuAvailable(products, dto.sku);
        await this.ensureSlugAvailable(products, slug);

        return products.createWithInventory(
          {
            name: dto.name,
            slug,
            sku: dto.sku,
            description: dto.description ?? null,
            price: toMoney(dto.price),
            categoryId: dto.categoryId ?? null,
            isActive: dto.isActive ?? true,
          },
          dto.initialStock ?? 0,
          createdBy,
        );
      },
    );

    return this.getProduct(product.id);
  }

  async update(id: number, dto: UpdateProductDto): Promise<ProductResponseDto> {
    const changes = toProductChanges(dto);

    if (Object.keys(changes).length === 0) {
      throw new BadRequestException('Provide at least one field to update');
    }

    await this.productsRepository.transaction(async (products) => {
      if (!(await products.lockById(id))) {
        throw new NotFoundException('Product not found');
      }

      await this.ensureCategoryExists(changes.categoryId);
      await products.lockUniqueValues({ sku: changes.sku, slug: changes.slug });

      if (changes.sku !== undefined) {
        await this.ensureSkuAvailable(products, changes.sku, id);
      }

      if (changes.slug !== undefined) {
        await this.ensureSlugAvailable(products, changes.slug, id);
      }

      await products.update(id, changes);
    });

    return this.getProduct(id);
  }

  private async getProduct(id: number): Promise<ProductResponseDto> {
    const product = await this.productsRepository.findById(id);

    if (!product) {
      throw new NotFoundException('Product not found');
    }

    return ProductResponseDto.fromEntity(product);
  }

  private async ensureSkuAvailable(
    products: ProductsRepository,
    sku: string,
    excludeId?: number,
  ): Promise<void> {
    if (await products.existsBySku(sku, excludeId)) {
      throw new ConflictException('A product with this SKU already exists');
    }
  }

  private async ensureSlugAvailable(
    products: ProductsRepository,
    slug: string,
    excludeId?: number,
  ): Promise<void> {
    if (await products.existsBySlug(slug, excludeId)) {
      throw new ConflictException('A product with this slug already exists');
    }
  }

  private async ensureCategoryExists(
    categoryId?: number | null,
  ): Promise<void> {
    if (categoryId === undefined || categoryId === null) {
      return;
    }

    if (!(await this.categoriesService.existsById(categoryId))) {
      throw new BadRequestException('Category not found');
    }
  }
}

function toMoney(amount: number): string {
  return amount.toFixed(2);
}

function toProductChanges(dto: UpdateProductDto): ProductChanges {
  const { price, ...fields } = dto;
  const changes: ProductChanges = { ...fields };

  if (price !== undefined) {
    changes.price = toMoney(price);
  }

  return changes;
}
