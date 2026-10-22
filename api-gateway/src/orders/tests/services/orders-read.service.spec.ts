import { NotFoundException } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { UserRole } from '../../../users/enums/user-role.enum.js';
import { OrderQueryDto } from '../../dto/order-query.dto.js';
import type { Order } from '../../entities/order.entity.js';
import { OrdersRepository } from '../../orders.repository.js';
import { OrdersReadService } from '../../services/orders-read.service.js';

function buildOrder(overrides: Partial<Order> = {}): Order {
  return {
    id: 1,
    orderNumber: 'ORD-1',
    userId: 9,
    status: 'paid',
    subtotal: '10.00',
    total: '10.00',
    paymentReference: 'mock_1',
    shippingAddress: { city: 'Sofia' },
    paidAt: new Date('2026-01-01T00:00:00Z'),
    createdAt: new Date('2026-01-01T00:00:00Z'),
    updatedAt: new Date('2026-01-01T00:00:00Z'),
    items: [
      {
        id: 3,
        productId: 5,
        productName: 'Mug',
        unitPrice: '5.00',
        quantity: 2,
        lineTotal: '10.00',
      },
    ],
    ...overrides,
  } as Order;
}

describe('OrdersReadService', () => {
  let service: OrdersReadService;
  let repository: {
    findPaginatedByUser: ReturnType<typeof vi.fn>;
    findWithItemsById: ReturnType<typeof vi.fn>;
  };

  const owner = {
    sub: 9,
    email: 'owner@example.test',
    role: UserRole.Customer,
  };

  beforeEach(async () => {
    repository = {
      findPaginatedByUser: vi.fn(),
      findWithItemsById: vi.fn().mockResolvedValue(buildOrder()),
    };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrdersReadService,
        { provide: OrdersRepository, useValue: repository },
      ],
    }).compile();

    service = moduleRef.get(OrdersReadService);
  });

  it("lists only the user's orders as summaries with pagination meta", async () => {
    const filters = Object.assign(new OrderQueryDto(), { page: 2, limit: 1 });
    repository.findPaginatedByUser.mockResolvedValue([[buildOrder()], 3]);

    const result = await service.findAllForUser(9, filters);

    expect(repository.findPaginatedByUser).toHaveBeenCalledWith(9, filters);
    expect(result.meta).toEqual({ page: 2, limit: 1, total: 3, totalPages: 3 });
    expect(result.data[0]).not.toHaveProperty('items');
    expect(result.data[0]).not.toHaveProperty('shippingAddress');
  });

  it('returns the full order to its owner', async () => {
    const result = await service.findOneForUser(1, owner);

    expect(result.items).toEqual([
      {
        id: 3,
        productId: 5,
        productName: 'Mug',
        unitPrice: '5.00',
        quantity: 2,
        lineTotal: '10.00',
      },
    ]);
    expect(result.shippingAddress).toEqual({ city: 'Sofia' });
  });

  it('lets an admin view any order', async () => {
    await expect(
      service.findOneForUser(1, { ...owner, sub: 1, role: UserRole.Admin }),
    ).resolves.toMatchObject({ id: 1 });
  });

  it("hides another customer's order as 404", async () => {
    await expect(
      service.findOneForUser(1, { ...owner, sub: 2 }),
    ).rejects.toThrow(NotFoundException);
  });

  it('throws 404 for a missing order', async () => {
    repository.findWithItemsById.mockResolvedValue(null);

    await expect(service.findOneForUser(99, owner)).rejects.toThrow(
      NotFoundException,
    );
  });
});
