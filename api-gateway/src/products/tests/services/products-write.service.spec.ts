import {
  BadRequestException,
  ConflictException,
  NotFoundException,
} from '@nestjs/common';
import { Test } from '@nestjs/testing';
import type { CreateProductDto } from '../../dto/create-product.dto.js';
import { ProductsCache } from '../../products.cache.js';
import { ProductsRepository } from '../../products.repository.js';
import { ProductValidator } from '../../validators/product.validator.js';
import { ProductsReadService } from '../../services/products-read.service.js';
import { ProductsWriteService } from '../../services/products-write.service.js';

describe('ProductsWriteService', () => {
  let service: ProductsWriteService;
  let transactionRepository: {
    lockUniqueValues: ReturnType<typeof vi.fn>;
    lockById: ReturnType<typeof vi.fn>;
    createWithInventory: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
  };
  let repository: { transaction: ReturnType<typeof vi.fn> };
  let readService: { findById: ReturnType<typeof vi.fn> };
  let cache: { invalidate: ReturnType<typeof vi.fn> };
  let validator: {
    resolveSlug: ReturnType<typeof vi.fn>;
    ensureHasChanges: ReturnType<typeof vi.fn>;
    ensureCategoryExists: ReturnType<typeof vi.fn>;
    ensureSkuAvailable: ReturnType<typeof vi.fn>;
    ensureSlugAvailable: ReturnType<typeof vi.fn>;
  };

  const savedProduct = { id: 7, name: 'Mechanical Keyboard' };
  const createDto: CreateProductDto = {
    name: 'Mechanical Keyboard',
    sku: 'MK-001',
    price: 89.9,
    categoryId: 3,
    initialStock: 25,
  };

  beforeEach(async () => {
    transactionRepository = {
      lockUniqueValues: vi.fn(),
      lockById: vi.fn().mockResolvedValue(true),
      createWithInventory: vi.fn().mockResolvedValue({ id: 7 }),
      update: vi.fn(),
    };
    repository = {
      transaction: vi.fn((work: (products: unknown) => Promise<unknown>) =>
        work(transactionRepository),
      ),
    };
    readService = { findById: vi.fn().mockResolvedValue(savedProduct) };
    cache = { invalidate: vi.fn() };
    validator = {
      resolveSlug: vi.fn().mockReturnValue('mechanical-keyboard'),
      ensureHasChanges: vi.fn(),
      ensureCategoryExists: vi.fn(),
      ensureSkuAvailable: vi.fn(),
      ensureSlugAvailable: vi.fn(),
    };

    const moduleRef = await Test.createTestingModule({
      providers: [
        ProductsWriteService,
        { provide: ProductsRepository, useValue: repository },
        { provide: ProductsReadService, useValue: readService },
        { provide: ProductsCache, useValue: cache },
        { provide: ProductValidator, useValue: validator },
      ],
    }).compile();

    service = moduleRef.get(ProductsWriteService);
  });

  describe('create', () => {
    it('locks and checks SKU and slug inside the transaction before inserting', async () => {
      await service.create(createDto, 1);

      expect(transactionRepository.lockUniqueValues).toHaveBeenCalledWith({
        sku: 'MK-001',
        slug: 'mechanical-keyboard',
      });
      expect(validator.ensureSkuAvailable).toHaveBeenCalledWith(
        transactionRepository,
        'MK-001',
      );
      expect(validator.ensureSlugAvailable).toHaveBeenCalledWith(
        transactionRepository,
        'mechanical-keyboard',
      );

      const lockOrder =
        transactionRepository.lockUniqueValues.mock.invocationCallOrder[0];
      const skuCheckOrder =
        validator.ensureSkuAvailable.mock.invocationCallOrder[0];
      const insertOrder =
        transactionRepository.createWithInventory.mock.invocationCallOrder[0];
      expect(lockOrder).toBeLessThan(skuCheckOrder);
      expect(skuCheckOrder).toBeLessThan(insertOrder);
    });

    it('inserts the mapped product with its initial stock and creator', async () => {
      await service.create(createDto, 1);

      expect(transactionRepository.createWithInventory).toHaveBeenCalledWith(
        {
          name: 'Mechanical Keyboard',
          slug: 'mechanical-keyboard',
          sku: 'MK-001',
          description: null,
          price: '89.90',
          categoryId: 3,
          isActive: true,
        },
        25,
        1,
      );
    });

    it('defaults the initial stock to 0', async () => {
      await service.create({ name: 'Mug', sku: 'MUG-1', price: 5 }, 1);

      expect(transactionRepository.createWithInventory).toHaveBeenCalledWith(
        expect.anything(),
        0,
        1,
      );
    });

    it('invalidates the cache after the transaction and returns the saved product', async () => {
      const result = await service.create(createDto, 1);

      expect(cache.invalidate).toHaveBeenCalledTimes(1);
      expect(repository.transaction.mock.invocationCallOrder[0]).toBeLessThan(
        cache.invalidate.mock.invocationCallOrder[0],
      );
      expect(readService.findById).toHaveBeenCalledWith(7);
      expect(result).toBe(savedProduct);
    });

    it('checks the category before opening a transaction', async () => {
      validator.ensureCategoryExists.mockRejectedValue(
        new BadRequestException('Category not found'),
      );

      await expect(service.create(createDto, 1)).rejects.toThrow(
        BadRequestException,
      );
      expect(repository.transaction).not.toHaveBeenCalled();
    });

    it('does not insert or invalidate when the SKU is taken', async () => {
      validator.ensureSkuAvailable.mockRejectedValue(new ConflictException());

      await expect(service.create(createDto, 1)).rejects.toThrow(
        ConflictException,
      );
      expect(transactionRepository.createWithInventory).not.toHaveBeenCalled();
      expect(cache.invalidate).not.toHaveBeenCalled();
    });
  });

  describe('update', () => {
    it('rejects an update without changes before opening a transaction', async () => {
      validator.ensureHasChanges.mockImplementation(() => {
        throw new BadRequestException();
      });

      await expect(service.update(7, {})).rejects.toThrow(BadRequestException);
      expect(repository.transaction).not.toHaveBeenCalled();
    });

    it('throws NotFound when the product row cannot be locked', async () => {
      transactionRepository.lockById.mockResolvedValue(false);

      await expect(service.update(99, { price: 10 })).rejects.toThrow(
        NotFoundException,
      );
      expect(transactionRepository.update).not.toHaveBeenCalled();
      expect(cache.invalidate).not.toHaveBeenCalled();
    });

    it('only checks uniqueness for the fields that change', async () => {
      await service.update(7, { price: 10 });

      expect(validator.ensureSkuAvailable).not.toHaveBeenCalled();
      expect(validator.ensureSlugAvailable).not.toHaveBeenCalled();
      expect(transactionRepository.update).toHaveBeenCalledWith(7, {
        price: '10.00',
      });
    });

    it('checks a new SKU and slug while excluding the product itself', async () => {
      await service.update(7, { sku: 'MK-002', slug: 'keyboard-v2' });

      expect(transactionRepository.lockUniqueValues).toHaveBeenCalledWith({
        sku: 'MK-002',
        slug: 'keyboard-v2',
      });
      expect(validator.ensureSkuAvailable).toHaveBeenCalledWith(
        transactionRepository,
        'MK-002',
        7,
      );
      expect(validator.ensureSlugAvailable).toHaveBeenCalledWith(
        transactionRepository,
        'keyboard-v2',
        7,
      );
    });

    it('invalidates the cache and returns the updated product', async () => {
      const result = await service.update(7, { name: 'Renamed' });

      expect(cache.invalidate).toHaveBeenCalledTimes(1);
      expect(readService.findById).toHaveBeenCalledWith(7);
      expect(result).toBe(savedProduct);
    });
  });
});
