import { Test } from '@nestjs/testing';
import { InventoryService } from '../../../inventory/inventory.service.js';
import { OrderStatus } from '../../enums/order-status.enum.js';
import { OrdersRepository } from '../../orders.repository.js';
import { OrderReleaseService } from '../../services/order-release.service.js';
import type { PlacedOrder } from '../../types/order.types.js';

describe('OrderReleaseService', () => {
  let service: OrderReleaseService;
  let transactionOrders: {
    lockStatusById: ReturnType<typeof vi.fn>;
    updateStatus: ReturnType<typeof vi.fn>;
  };
  let inventoryService: { release: ReturnType<typeof vi.fn> };

  const manager = { name: 'transaction-manager' };
  const order: PlacedOrder = {
    id: 10,
    orderNumber: 'ORD-1',
    userId: 9,
    total: '45.00',
    items: [{ productId: 1, quantity: 2 }],
  };

  beforeEach(async () => {
    transactionOrders = {
      lockStatusById: vi.fn().mockResolvedValue(OrderStatus.Pending),
      updateStatus: vi.fn(),
    };
    inventoryService = { release: vi.fn() };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrderReleaseService,
        {
          provide: OrdersRepository,
          useValue: {
            transaction: (
              work: (orders: unknown, manager: unknown) => Promise<unknown>,
            ) => work(transactionOrders, manager),
          },
        },
        { provide: InventoryService, useValue: inventoryService },
      ],
    }).compile();

    service = moduleRef.get(OrderReleaseService);
  });

  it('marks a pending order as payment_failed and returns its stock', async () => {
    await expect(service.releasePending(order)).resolves.toBe(true);

    expect(transactionOrders.lockStatusById).toHaveBeenCalledWith(10);
    expect(transactionOrders.updateStatus).toHaveBeenCalledWith(
      10,
      OrderStatus.PaymentFailed,
    );
    expect(inventoryService.release).toHaveBeenCalledWith(
      manager,
      order.items,
      { orderId: 10, userId: 9 },
    );
  });

  it.each([OrderStatus.Paid, OrderStatus.PaymentFailed, null])(
    'leaves an order alone when its status is %s (no double release)',
    async (status) => {
      transactionOrders.lockStatusById.mockResolvedValue(status);

      await expect(service.releasePending(order)).resolves.toBe(false);

      expect(transactionOrders.updateStatus).not.toHaveBeenCalled();
      expect(inventoryService.release).not.toHaveBeenCalled();
    },
  );
});
