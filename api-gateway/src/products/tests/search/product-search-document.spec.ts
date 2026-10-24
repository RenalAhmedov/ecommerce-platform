import {
  type IndexableProduct,
  isSearchable,
  toSearchDocument,
} from '../../search/product-search-document.js';

describe('product search document', () => {
  const product: IndexableProduct = {
    id: 7,
    name: 'Blue Mug',
    sku: 'MUG-1',
    description: null,
    categoryId: 3,
    price: '12.50',
    isActive: true,
    createdAt: new Date('2026-10-24T08:00:00Z'),
    deletedAt: null,
  };

  it('holds only what is searched, filtered or sorted on', () => {
    expect(toSearchDocument(product)).toEqual({
      id: 7,
      name: 'Blue Mug',
      sku: 'MUG-1',
      description: null,
      categoryId: 3,
      price: 12.5,
      createdAt: '2026-10-24T08:00:00.000Z',
    });
  });

  it('only makes active, not deleted products searchable', () => {
    expect(isSearchable(product)).toBe(true);
    expect(isSearchable({ ...product, isActive: false })).toBe(false);
    expect(isSearchable({ ...product, deletedAt: new Date() })).toBe(false);
  });
});
