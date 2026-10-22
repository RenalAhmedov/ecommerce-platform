import {
  toNewProduct,
  toProductChanges,
} from '../../mappers/product.mapper.js';

describe('product.mapper', () => {
  describe('toNewProduct', () => {
    it('fills defaults for the optional fields', () => {
      expect(
        toNewProduct({ name: 'Mug', sku: 'MUG-1', price: 5 }, 'mug'),
      ).toEqual({
        name: 'Mug',
        slug: 'mug',
        sku: 'MUG-1',
        description: null,
        price: '5.00',
        categoryId: null,
        isActive: true,
      });
    });

    it('keeps the provided optional fields', () => {
      expect(
        toNewProduct(
          {
            name: 'Lamp',
            sku: 'L-1',
            price: 19.9,
            description: 'Warm light',
            categoryId: 2,
            isActive: false,
          },
          'lamp',
        ),
      ).toMatchObject({
        description: 'Warm light',
        price: '19.90',
        categoryId: 2,
        isActive: false,
      });
    });
  });

  describe('toProductChanges', () => {
    it('includes only the fields that were sent', () => {
      expect(toProductChanges({ name: 'Renamed' })).toEqual({
        name: 'Renamed',
      });
    });

    it('formats the price as money', () => {
      expect(toProductChanges({ price: 7 })).toEqual({ price: '7.00' });
    });

    it('keeps null so a field can be cleared', () => {
      expect(toProductChanges({ description: null, categoryId: null })).toEqual(
        {
          description: null,
          categoryId: null,
        },
      );
    });

    it('returns an empty object for an empty update', () => {
      expect(toProductChanges({})).toEqual({});
    });
  });
});
