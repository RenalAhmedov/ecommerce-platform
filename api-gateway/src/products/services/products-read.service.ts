import { Injectable, NotFoundException } from '@nestjs/common';
import type { Paginated } from '../../common/interfaces/paginated.interface.js';
import { toPaginated } from '../../common/utils/paginate.js';
import type { ProductQueryDto } from '../dto/product-query.dto.js';
import { ProductResponseDto } from '../dto/product-response.dto.js';
import { ProductsCache } from '../products.cache.js';
import { ProductsRepository } from '../products.repository.js';
import { isSearch } from '../search/product-search.query.js';
import { ProductSearchService } from '../search/product-search.service.js';
import { ProductValidator } from '../validators/product.validator.js';

@Injectable()
export class ProductsReadService {
  constructor(
    private readonly productsRepository: ProductsRepository,
    private readonly productsCache: ProductsCache,
    private readonly productValidator: ProductValidator,
    private readonly productSearchService: ProductSearchService,
  ) {}

  findAll(filters: ProductQueryDto): Promise<Paginated<ProductResponseDto>> {
    this.productValidator.ensureValidPriceRange(filters);

    return this.productsCache.list(filters, () =>
      this.loadProductList(filters),
    );
  }

  findOne(id: number): Promise<ProductResponseDto> {
    return this.productsCache.detail(id, () => this.loadActiveProduct(id));
  }

  private async loadProductList(
    filters: ProductQueryDto,
  ): Promise<Paginated<ProductResponseDto>> {
    const [products, total] = isSearch(filters)
      ? await this.productSearchService.search(filters)
      : await this.productsRepository.findActivePaginated(filters);

    return toPaginated(
      products.map((product) => ProductResponseDto.fromEntity(product)),
      total,
      filters,
    );
  }

  private async loadActiveProduct(id: number): Promise<ProductResponseDto> {
    const product = await this.productsRepository.findActiveById(id);

    if (!product) {
      throw new NotFoundException('Product not found');
    }

    return ProductResponseDto.fromEntity(product);
  }
}
