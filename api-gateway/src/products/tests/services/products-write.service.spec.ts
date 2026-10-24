import {
  BadRequestException,
  ConflictException,
  NotFoundException,
} from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { InventoryService } from '../../../inventory/inventory.service.js';
import type { CreateProductDto } from '../../dto/create-product.dto.js';
import { ProductChangeNotifier } from '../../product-change.notifier.js';
import { ProductsRepository } from '../../products.repository.js';
import { ProductValidator } from '../../validators/product.validator.js';
import { ProductsWriteService } from '../../services/products-write.service.js';

describe('ProductsWriteService', () => {
  let service: ProductsWriteService;
  let transactionRepository: {
    lockUniqueValues: ReturnType<typeof vi.fn>;
    lockById: ReturnType<typeof vi.fn>;
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
    getById: ReturnType<typeof vi.fn>;
  };
  let repository: { transaction: ReturnType<typeof vi.fn> };
  let inventoryService: { initializeStock: ReturnType<typeof vi.fn> };
  let notifier: { productsChanged: ReturnType<typeof vi.fn> };
  let validator: {
    resolveSlug: ReturnType<typeof vi.fn>;
    ensureHasChanges: ReturnType<typeof vi.fn>;
    ensureCategoryExists: ReturnType<typeof vi.fn>;
    ensureSkuAvailable: ReturnType<typeof vi.fn>;
    ensureSlugAvailable: ReturnType<typeof vi.fn>;
  };

  const manager = { name: 'transaction-manager' };
  const savedProduct = {
    id: 7,
    name: 'Mechanical Keyboard',
    category: null,
    inventory: { quantity: 25 },
  };
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
      create: vi.fn().mockResolvedValue({ id: 7 }),
      update: vi.fn(),
      getById: vi.fn().mockResolvedValue(savedProduct),
    };
    repository = {
      transaction: vi.fn(
        (work: (products: unknown, manager: unknown) => Promise<unknown>) =>
          work(transactionRepository, manager),
      ),
    };
    inventoryService = { initializeStock: vi.fn() };
    notifier = { productsChanged: vi.fn() };
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
        { provide: ProductChangeNotifier, useValue: notifier },
        { provide: ProductValidator, useValue: validator },
        { provide: InventoryService, useValue: inventoryService },
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
        transactionRepository.create.mock.invocationCallOrder[0];
      expect(lockOrder).toBeLessThan(skuCheckOrder);
      expect(skuCheckOrder).toBeLessThan(insertOrder);
    });

    it('inserts the mapped product', async () => {
      await service.create(createDto, 1);

      expect(transactionRepository.create).toHaveBeenCalledWith({
        name: 'Mechanical Keyboard',
        slug: 'mechanical-keyboard',
        sku: 'MK-001',
        description: null,
        price: '89.90',
        categoryId: 3,
        isActive: true,
      });
    });

    it('initializes stock for the new product in the same transaction', async () => {
      await service.create(createDto, 1);

      expect(inventoryService.initializeStock).toHaveBeenCalledWith(
        manager,
        7,
        25,
        1,
      );
    });

    it('defaults the initial stock to 0', async () => {
      await service.create({ name: 'Mug', sku: 'MUG-1', price: 5 }, 1);

      expect(inventoryService.initializeStock).toHaveBeenCalledWith(
        manager,
        7,
        0,
        1,
      );
    });

    it('returns the product read inside the transaction and announces the change after it', async () => {
      const result = await service.create(createDto, 1);

      expect(transactionRepository.getById).toHaveBeenCalledWith(7);
      expect(result).toMatchObject({ id: 7, category: null, stock: 25 });
      expect(notifier.productsChanged).toHaveBeenCalledTimes(1);
      expect(
        transactionRepository.getById.mock.invocationCallOrder[0],
      ).toBeLessThan(notifier.productsChanged.mock.invocationCallOrder[0]);
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

    it('does not insert or announce anything when the SKU is taken', async () => {
      validator.ensureSkuAvailable.mockRejectedValue(new ConflictException());

      await expect(service.create(createDto, 1)).rejects.toThrow(
        ConflictException,
      );
      expect(transactionRepository.create).not.toHaveBeenCalled();
      expect(inventoryService.initializeStock).not.toHaveBeenCalled();
      expect(notifier.productsChanged).not.toHaveBeenCalled();
    });
  });

  describe('update', () => {
    it('rejects an update without changes before opening a transaction', async () => {
      validator.ensureHasChanges.mockImplementation(() => {
        throw new BadRequestException();
      });

      await expect(service.update(7, {}, 1)).rejects.toThrow(
        BadRequestException,
      );
      expect(repository.transaction).not.toHaveBeenCalled();
    });

    it('checks a new category before opening a transaction', async () => {
      validator.ensureCategoryExists.mockRejectedValue(
        new BadRequestException('Category not found'),
      );

      await expect(service.update(7, { categoryId: 404 }, 1)).rejects.toThrow(
        'Category not found',
      );
      expect(repository.transaction).not.toHaveBeenCalled();
    });

    it('throws NotFound when the product row cannot be locked', async () => {
      transactionRepository.lockById.mockResolvedValue(false);

      await expect(service.update(99, { price: 10 }, 1)).rejects.toThrow(
        NotFoundException,
      );
      expect(transactionRepository.update).not.toHaveBeenCalled();
      expect(notifier.productsChanged).not.toHaveBeenCalled();
    });

    it('only checks uniqueness for the fields that change', async () => {
      await service.update(7, { price: 10 }, 1);

      expect(validator.ensureSkuAvailable).not.toHaveBeenCalled();
      expect(validator.ensureSlugAvailable).not.toHaveBeenCalled();
      expect(transactionRepository.update).toHaveBeenCalledWith(7, {
        price: '10.00',
      });
    });

    it('checks a new SKU and slug while excluding the product itself', async () => {
      await service.update(7, { sku: 'MK-002', slug: 'keyboard-v2' }, 1);

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

    it('returns the product read after the update and announces the change', async () => {
      const result = await service.update(7, { name: 'Renamed' }, 1);

      expect(
        transactionRepository.update.mock.invocationCallOrder[0],
      ).toBeLessThan(transactionRepository.getById.mock.invocationCallOrder[0]);
      expect(result).toMatchObject({ id: 7, stock: 25 });
      expect(notifier.productsChanged).toHaveBeenCalledTimes(1);
    });
  });
});
