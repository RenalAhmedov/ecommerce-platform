import { BadRequestException } from '@nestjs/common';
import { MAX_ORDER_TOTAL_CENTS } from '../../constants/order.constants.js';
import { OrderValidator } from '../../validators/order.validator.js';

describe('OrderValidator', () => {
  const validator = new OrderValidator();

  describe('ensureProductsPurchasable', () => {
    it('passes when every product was found', () => {
      expect(() =>
        validator.ensureProductsPurchasable(
          [{ productId: 1, quantity: 1 }],
          [{ id: 1, name: 'Keyboard', price: '10.00' }],
        ),
      ).not.toThrow();
    });

    it('lists every unavailable product', () => {
      expect(() =>
        validator.ensureProductsPurchasable(
          [
            { productId: 1, quantity: 1 },
            { productId: 2, quantity: 1 },
            { productId: 3, quantity: 1 },
          ],
          [{ id: 2, name: 'Mug', price: '5.00' }],
        ),
      ).toThrow(new BadRequestException('Products not available: 1, 3'));
    });
  });

  describe('ensureWithinTotalLimit', () => {
    it('accepts the largest amount the column can store', () => {
      expect(() =>
        validator.ensureWithinTotalLimit(MAX_ORDER_TOTAL_CENTS),
      ).not.toThrow();
    });

    it('rejects anything above it', () => {
      expect(() =>
        validator.ensureWithinTotalLimit(MAX_ORDER_TOTAL_CENTS + 1),
      ).toThrow(BadRequestException);
    });
  });
});
