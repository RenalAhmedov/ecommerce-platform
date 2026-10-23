import type { CreateOrderDto } from '../../dto/create-order.dto.js';
import type { Order } from '../../entities/order.entity.js';
import { OrderStatus } from '../../enums/order-status.enum.js';
import {
  sumLineTotals,
  toNewOrder,
  toOrderLines,
  toPlacedOrder,
} from '../../mappers/order.mapper.js';

describe('order mapper', () => {
  const products = [
    { id: 1, name: 'Keyboard', price: '19.99' },
    { id: 2, name: 'Sticker', price: '0.10' },
  ];

  describe('toOrderLines', () => {
    it('snapshots name and price from the database and computes exact line totals', () => {
      const lines = toOrderLines(
        [
          { productId: 1, quantity: 3 },
          { productId: 2, quantity: 3 },
        ],
        products,
      );

      expect(lines).toEqual([
        {
          productId: 1,
          productName: 'Keyboard',
          unitPrice: '19.99',
          quantity: 3,
          lineTotal: '59.97',
        },
        {
          productId: 2,
          productName: 'Sticker',
          unitPrice: '0.10',
          quantity: 3,
          lineTotal: '0.30',
        },
      ]);
    });

    it('fails loudly if a product was not loaded', () => {
      expect(() =>
        toOrderLines([{ productId: 99, quantity: 1 }], products),
      ).toThrow('Product 99 was not loaded');
    });
  });

  it('sums line totals in cents without floating point drift', () => {
    const lines = toOrderLines(
      [
        { productId: 2, quantity: 1 },
        { productId: 2, quantity: 2 },
      ],
      products,
    );

    expect(sumLineTotals(lines)).toBe(30);
  });

  it('builds a pending order with subtotal and total from cents', () => {
    const dto = {
      shippingAddress: { city: 'Sofia' },
    } as unknown as CreateOrderDto;

    const order = toNewOrder(9, dto, 5_997);

    expect(order).toMatchObject({
      userId: 9,
      status: OrderStatus.Pending,
      subtotal: '59.97',
      total: '59.97',
      shippingAddress: { city: 'Sofia' },
    });
    expect(order.orderNumber).toMatch(/^ORD-\d{8}-[0-9A-F]{8}$/);
  });

  it('maps a stored order to the shape used for payment and release', () => {
    const order = {
      id: 4,
      orderNumber: 'ORD-1',
      userId: 9,
      total: '10.00',
      items: [{ productId: 1, quantity: 2, productName: 'Keyboard' }],
    } as unknown as Order;

    expect(toPlacedOrder(order)).toEqual({
      id: 4,
      orderNumber: 'ORD-1',
      userId: 9,
      total: '10.00',
      items: [{ productId: 1, quantity: 2 }],
    });
  });
});
