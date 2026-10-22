import { ConflictException } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import type { EntityManager } from 'typeorm';
import { InventoryMovementReason } from '../enums/inventory-movement-reason.enum.js';
import { InventoryRepository } from '../inventory.repository.js';
import { InventoryService } from '../inventory.service.js';

describe('InventoryService', () => {
  let service: InventoryService;
  let repository: { withManager: ReturnType<typeof vi.fn> };
  let transactionRepository: {
    create: ReturnType<typeof vi.fn>;
    lockByProductIds: ReturnType<typeof vi.fn>;
    adjustQuantity: ReturnType<typeof vi.fn>;
    recordMovements: ReturnType<typeof vi.fn>;
  };

  const manager = {} as EntityManager;
  const context = { orderId: 50, userId: 9 };

  beforeEach(async () => {
    transactionRepository = {
      create: vi.fn(),
      lockByProductIds: vi.fn().mockResolvedValue([]),
      adjustQuantity: vi.fn(),
      recordMovements: vi.fn(),
    };
    repository = {
      withManager: vi.fn().mockReturnValue(transactionRepository),
    };

    const moduleRef = await Test.createTestingModule({
      providers: [
        InventoryService,
        { provide: InventoryRepository, useValue: repository },
      ],
    }).compile();

    service = moduleRef.get(InventoryService);
  });

  const stockRows = (rows: Record<number, number>) =>
    Object.entries(rows).map(([productId, quantity]) => ({
      productId: Number(productId),
      quantity,
    }));

  describe('initializeStock', () => {
    it('creates the stock row inside the given transaction with an initial movement', async () => {
      await service.initializeStock(manager, 7, 25, 1);

      expect(repository.withManager).toHaveBeenCalledWith(manager);
      expect(transactionRepository.create).toHaveBeenCalledWith(7, 25);
      expect(transactionRepository.recordMovements).toHaveBeenCalledWith([
        {
          productId: 7,
          userId: 1,
          change: 25,
          reason: InventoryMovementReason.Initial,
        },
      ]);
    });

    it('records no movement for zero initial stock', async () => {
      await service.initializeStock(manager, 7, 0, 1);

      expect(transactionRepository.create).toHaveBeenCalledWith(7, 0);
      expect(transactionRepository.recordMovements).not.toHaveBeenCalled();
    });
  });

  describe('reserve', () => {
    it('locks rows sorted by product id, decrements and records sales', async () => {
      transactionRepository.lockByProductIds.mockResolvedValue(
        stockRows({ 2: 10, 5: 10 }),
      );

      await service.reserve(
        manager,
        [
          { productId: 5, quantity: 1 },
          { productId: 2, quantity: 3 },
        ],
        context,
      );

      expect(transactionRepository.lockByProductIds).toHaveBeenCalledWith([
        2, 5,
      ]);
      expect(transactionRepository.adjustQuantity.mock.calls).toEqual([
        [2, -3],
        [5, -1],
      ]);
      expect(transactionRepository.recordMovements).toHaveBeenCalledWith([
        { productId: 2, orderId: 50, userId: 9, change: -3, reason: 'sale' },
        { productId: 5, orderId: 50, userId: 9, change: -1, reason: 'sale' },
      ]);
    });

    it('merges duplicate lines for the same product before checking stock', async () => {
      transactionRepository.lockByProductIds.mockResolvedValue(
        stockRows({ 4: 5 }),
      );

      const error = await service
        .reserve(
          manager,
          [
            { productId: 4, quantity: 3 },
            { productId: 4, quantity: 3 },
          ],
          context,
        )
        .catch((caught: unknown) => caught);

      expect((error as ConflictException).getResponse()).toMatchObject({
        message: ['Insufficient stock for product 4: requested 6, available 5'],
      });
      expect(transactionRepository.lockByProductIds).toHaveBeenCalledWith([4]);
    });

    it('reports every shortage, treats a missing stock row as 0 and changes nothing', async () => {
      transactionRepository.lockByProductIds.mockResolvedValue(
        stockRows({ 1: 2, 2: 100 }),
      );

      const error = await service
        .reserve(
          manager,
          [
            { productId: 1, quantity: 3 },
            { productId: 2, quantity: 1 },
            { productId: 3, quantity: 1 },
          ],
          context,
        )
        .catch((caught: unknown) => caught);

      expect(error).toBeInstanceOf(ConflictException);
      expect((error as ConflictException).getResponse()).toMatchObject({
        message: [
          'Insufficient stock for product 1: requested 3, available 2',
          'Insufficient stock for product 3: requested 1, available 0',
        ],
      });
      expect(transactionRepository.adjustQuantity).not.toHaveBeenCalled();
      expect(transactionRepository.recordMovements).not.toHaveBeenCalled();
    });

    it('does nothing for an empty item list', async () => {
      await service.reserve(manager, [], context);

      expect(repository.withManager).not.toHaveBeenCalled();
    });
  });

  describe('release', () => {
    it('returns stock without locking first and records cancellations', async () => {
      await service.release(
        manager,
        [
          { productId: 8, quantity: 2 },
          { productId: 3, quantity: 1 },
        ],
        context,
      );

      expect(transactionRepository.lockByProductIds).not.toHaveBeenCalled();
      expect(transactionRepository.adjustQuantity.mock.calls).toEqual([
        [3, 1],
        [8, 2],
      ]);
      expect(transactionRepository.recordMovements).toHaveBeenCalledWith([
        {
          productId: 3,
          orderId: 50,
          userId: 9,
          change: 1,
          reason: 'cancellation',
        },
        {
          productId: 8,
          orderId: 50,
          userId: 9,
          change: 2,
          reason: 'cancellation',
        },
      ]);
    });
  });
});
