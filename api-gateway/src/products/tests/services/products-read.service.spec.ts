import { BadRequestException, NotFoundException } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { ProductQueryDto } from '../../dto/product-query.dto.js';
import type { Product } from '../../entities/product.entity.js';
import { ProductsCache } from '../../products.cache.js';
import { ProductsRepository } from '../../products.repository.js';
import { ProductValidator } from '../../validators/product.validator.js';
import { ProductsReadService } from '../../services/products-read.service.js';

function buildProduct(overrides: Partial<Product> = {}): Product {
  return {
    id: 1,
    name: 'Mechanical Keyboard',
    slug: 'mechanical-keyboard',
    sku: 'MK-001',
    description: null,
    price: '89.90',
    imagePath: null,
    isActive: true,
    category: { id: 3, name: 'Electronics' },
    inventory: { id: 1, quantity: 25 },
    createdAt: new Date('2026-01-01T00:00:00Z'),
    updatedAt: new Date('2026-01-02T00:00:00Z'),
    ...overrides,
  } as Product;
}

describe('ProductsReadService', () => {
  let service: ProductsReadService;
  let repository: {
    findActivePaginated: ReturnType<typeof vi.fn>;
    findActiveById: ReturnType<typeof vi.fn>;
    findById: ReturnType<typeof vi.fn>;
  };
  let cache: {
    list: ReturnType<typeof vi.fn>;
    detail: ReturnType<typeof vi.fn>;
  };
  let validator: { ensureValidPriceRange: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    repository = {
      findActivePaginated: vi.fn(),
      findActiveById: vi.fn(),
      findById: vi.fn(),
    };
    cache = {
      list: vi.fn((_filters: unknown, load: () => Promise<unknown>) => load()),
      detail: vi.fn((_id: unknown, load: () => Promise<unknown>) => load()),
    };
    validator = { ensureValidPriceRange: vi.fn() };

    const moduleRef = await Test.createTestingModule({
      providers: [
        ProductsReadService,
        { provide: ProductsRepository, useValue: repository },
        { provide: ProductsCache, useValue: cache },
        { provide: ProductValidator, useValue: validator },
      ],
    }).compile();

    service = moduleRef.get(ProductsReadService);
  });

  describe('findAll', () => {
    it('validates the filters before touching the cache', async () => {
      validator.ensureValidPriceRange.mockImplementation(() => {
        throw new BadRequestException();
      });

      expect(() => service.findAll(new ProductQueryDto())).toThrow(
        BadRequestException,
      );
      expect(cache.list).not.toHaveBeenCalled();
    });

    it('reads through the cache with the given filters', async () => {
      const filters = new ProductQueryDto();
      repository.findActivePaginated.mockResolvedValue([[], 0]);

      await service.findAll(filters);

      expect(cache.list).toHaveBeenCalledWith(filters, expect.any(Function));
    });

    it('returns the cached page without loading from the repository', async () => {
      const cached = {
        data: [],
        meta: { page: 1, limit: 20, total: 0, totalPages: 0 },
      };
      cache.list.mockResolvedValue(cached);

      await expect(service.findAll(new ProductQueryDto())).resolves.toBe(
        cached,
      );
      expect(repository.findActivePaginated).not.toHaveBeenCalled();
    });

    it('maps products and pagination meta on a cache miss', async () => {
      const filters = Object.assign(new ProductQueryDto(), {
        page: 2,
        limit: 2,
      });
      repository.findActivePaginated.mockResolvedValue([
        [buildProduct({ id: 3 }), buildProduct({ id: 4, category: null })],
        5,
      ]);

      const result = await service.findAll(filters);

      expect(repository.findActivePaginated).toHaveBeenCalledWith(filters);
      expect(result.meta).toEqual({
        page: 2,
        limit: 2,
        total: 5,
        totalPages: 3,
      });
      expect(result.data.map((product) => product.id)).toEqual([3, 4]);
      expect(result.data[0]).toMatchObject({
        price: '89.90',
        category: { id: 3, name: 'Electronics' },
        stock: 25,
      });
      expect(result.data[1].category).toBeNull();
    });
  });

  describe('findOne', () => {
    it('returns an active product through the cache', async () => {
      repository.findActiveById.mockResolvedValue(buildProduct({ id: 7 }));

      const result = await service.findOne(7);

      expect(cache.detail).toHaveBeenCalledWith(7, expect.any(Function));
      expect(repository.findActiveById).toHaveBeenCalledWith(7);
      expect(result).toMatchObject({ id: 7, slug: 'mechanical-keyboard' });
    });

    it('reports a product without a stock row as stock 0', async () => {
      repository.findActiveById.mockResolvedValue(
        buildProduct({ inventory: undefined }),
      );

      await expect(service.findOne(1)).resolves.toMatchObject({ stock: 0 });
    });

    it('throws NotFound for a missing or inactive product', async () => {
      repository.findActiveById.mockResolvedValue(null);

      await expect(service.findOne(99)).rejects.toThrow(NotFoundException);
    });
  });

  describe('findById', () => {
    it('returns the product without using the cache', async () => {
      repository.findById.mockResolvedValue(buildProduct({ isActive: false }));

      const result = await service.findById(1);

      expect(result.isActive).toBe(false);
      expect(cache.detail).not.toHaveBeenCalled();
    });

    it('throws NotFound when the product does not exist', async () => {
      repository.findById.mockResolvedValue(null);

      await expect(service.findById(99)).rejects.toThrow(NotFoundException);
    });
  });
});
