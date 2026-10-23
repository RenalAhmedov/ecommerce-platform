import { BadRequestException, ConflictException } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { InventoryService } from '../../../inventory/inventory.service.js';
import { ProductCatalogService } from '../../../products/services/product-catalog.service.js';
import type { CreateOrderDto } from '../../dto/create-order.dto.js';
import { OrderStatus } from '../../enums/order-status.enum.js';
import { OrdersRepository } from '../../orders.repository.js';
import { OrderPlacementService } from '../../services/order-placement.service.js';
import { OrderValidator } from '../../validators/order.validator.js';

describe('OrderPlacementService', () => {
  let service: OrderPlacementService;
  let transactionOrders: { createWithItems: ReturnType<typeof vi.fn> };
  let ordersRepository: { transaction: ReturnType<typeof vi.fn> };
  let catalog: { findPurchasable: ReturnType<typeof vi.fn> };
  let inventoryService: { reserve: ReturnType<typeof vi.fn> };

  const manager = { name: 'transaction-manager' };
  const dto = {
    items: [
      { productId: 1, quantity: 2 },
      { productId: 2, quantity: 1 },
    ],
    shippingAddress: { fullName: 'Ada', city: 'Sofia' },
    paymentToken: 'tok_visa',
  } as unknown as CreateOrderDto;

  beforeEach(async () => {
    transactionOrders = {
      createWithItems: vi
        .fn()
        .mockResolvedValue({ id: 10, orderNumber: 'ORD-1', total: '45.00' }),
    };
    ordersRepository = {
      transaction: vi.fn(
        (work: (orders: unknown, manager: unknown) => Promise<unknown>) =>
          work(transactionOrders, manager),
      ),
    };
    catalog = {
      findPurchasable: vi.fn().mockResolvedValue([
        { id: 1, name: 'Keyboard', price: '20.00' },
        { id: 2, name: 'Mug', price: '5.00' },
      ]),
    };
    inventoryService = { reserve: vi.fn() };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrderPlacementService,
        OrderValidator,
        { provide: OrdersRepository, useValue: ordersRepository },
        { provide: ProductCatalogService, useValue: catalog },
        { provide: InventoryService, useValue: inventoryService },
      ],
    }).compile();

    service = moduleRef.get(OrderPlacementService);
  });

  it('loads the products inside the transaction', async () => {
    await service.place(9, dto);

    expect(catalog.findPurchasable).toHaveBeenCalledWith(manager, [1, 2]);
  });

  it('creates a pending order priced from the database, not the client', async () => {
    await service.place(9, dto);

    const [order, lines] = transactionOrders.createWithItems.mock.calls[0] as [
      Record<string, unknown>,
      unknown[],
    ];
    expect(order).toMatchObject({
      userId: 9,
      status: OrderStatus.Pending,
      subtotal: '45.00',
      total: '45.00',
    });
    expect(lines).toEqual([
      expect.objectContaining({ productId: 1, lineTotal: '40.00' }),
      expect.objectContaining({ productId: 2, lineTotal: '5.00' }),
    ]);
  });

  it('reserves stock for the new order after inserting it', async () => {
    await service.place(9, dto);

    expect(inventoryService.reserve).toHaveBeenCalledWith(manager, dto.items, {
      orderId: 10,
      userId: 9,
    });
    expect(
      transactionOrders.createWithItems.mock.invocationCallOrder[0],
    ).toBeLessThan(inventoryService.reserve.mock.invocationCallOrder[0]);
  });

  it('returns what payment and release need', async () => {
    await expect(service.place(9, dto)).resolves.toEqual({
      id: 10,
      orderNumber: 'ORD-1',
      userId: 9,
      total: '45.00',
      items: dto.items,
    });
  });

  it('rejects unavailable products before inserting anything', async () => {
    catalog.findPurchasable.mockResolvedValue([
      { id: 1, name: 'Keyboard', price: '20.00' },
    ]);

    await expect(service.place(9, dto)).rejects.toThrow(BadRequestException);
    expect(transactionOrders.createWithItems).not.toHaveBeenCalled();
    expect(inventoryService.reserve).not.toHaveBeenCalled();
  });

  it('rejects totals above the column limit before inserting anything', async () => {
    catalog.findPurchasable.mockResolvedValue([
      { id: 1, name: 'Yacht', price: '9999999999.99' },
      { id: 2, name: 'Mug', price: '5.00' },
    ]);

    await expect(service.place(9, dto)).rejects.toThrow(
      'Order total exceeds the maximum allowed amount',
    );
    expect(transactionOrders.createWithItems).not.toHaveBeenCalled();
  });

  it('lets a stock conflict escape so the transaction rolls back', async () => {
    inventoryService.reserve.mockRejectedValue(new ConflictException());

    await expect(service.place(9, dto)).rejects.toThrow(ConflictException);
  });
});
