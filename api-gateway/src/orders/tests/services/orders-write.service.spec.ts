import { ConflictException, HttpException, HttpStatus } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { ProductsCache } from '../../../products/products.cache.js';
import type { CreateOrderDto } from '../../dto/create-order.dto.js';
import { OrdersRepository } from '../../orders.repository.js';
import { OrderPaymentService } from '../../services/order-payment.service.js';
import { OrderPlacementService } from '../../services/order-placement.service.js';
import { OrdersWriteService } from '../../services/orders-write.service.js';

describe('OrdersWriteService', () => {
  let service: OrdersWriteService;
  let placement: { place: ReturnType<typeof vi.fn> };
  let payment: { settle: ReturnType<typeof vi.fn> };
  let ordersRepository: { getWithItemsById: ReturnType<typeof vi.fn> };
  let cache: { invalidate: ReturnType<typeof vi.fn> };

  const dto = { paymentToken: 'tok_visa' } as CreateOrderDto;
  const placed = {
    id: 10,
    orderNumber: 'ORD-1',
    userId: 9,
    total: '45.00',
    items: [],
  };

  beforeEach(async () => {
    placement = { place: vi.fn().mockResolvedValue(placed) };
    payment = { settle: vi.fn().mockResolvedValue(true) };
    ordersRepository = {
      getWithItemsById: vi.fn().mockResolvedValue({
        id: 10,
        orderNumber: 'ORD-1',
        status: 'paid',
        items: [],
      }),
    };
    cache = { invalidate: vi.fn() };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrdersWriteService,
        { provide: OrderPlacementService, useValue: placement },
        { provide: OrderPaymentService, useValue: payment },
        { provide: OrdersRepository, useValue: ordersRepository },
        { provide: ProductsCache, useValue: cache },
      ],
    }).compile();

    service = moduleRef.get(OrdersWriteService);
  });

  it('places, settles with the client token and returns the stored order', async () => {
    const result = await service.create(9, dto);

    expect(placement.place).toHaveBeenCalledWith(9, dto);
    expect(payment.settle).toHaveBeenCalledWith(placed, 'tok_visa');
    expect(ordersRepository.getWithItemsById).toHaveBeenCalledWith(10);
    expect(result).toMatchObject({ id: 10, status: 'paid', items: [] });
  });

  it('invalidates the products cache because stock changed', async () => {
    await service.create(9, dto);

    expect(cache.invalidate).toHaveBeenCalledTimes(1);
  });

  it('answers 402 with the order number when the payment is declined', async () => {
    payment.settle.mockResolvedValue(false);

    const error = await service
      .create(9, dto)
      .catch((caught: unknown) => caught);

    expect(error).toBeInstanceOf(HttpException);
    expect((error as HttpException).getStatus()).toBe(
      HttpStatus.PAYMENT_REQUIRED,
    );
    expect((error as HttpException).message).toContain('ORD-1');
    expect(cache.invalidate).toHaveBeenCalledTimes(1);
  });

  it('still invalidates the cache when settling crashes', async () => {
    payment.settle.mockRejectedValue(new Error('database gone'));

    await expect(service.create(9, dto)).rejects.toThrow('database gone');
    expect(cache.invalidate).toHaveBeenCalledTimes(1);
  });

  it('does not charge or invalidate when placement fails', async () => {
    placement.place.mockRejectedValue(new ConflictException());

    await expect(service.create(9, dto)).rejects.toThrow(ConflictException);
    expect(payment.settle).not.toHaveBeenCalled();
    expect(cache.invalidate).not.toHaveBeenCalled();
  });
});
