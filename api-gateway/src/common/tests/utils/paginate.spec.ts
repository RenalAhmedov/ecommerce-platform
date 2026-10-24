import { toPaginated } from '../../utils/paginate.js';

describe('toPaginated', () => {
  it('wraps a page of data with its meta', () => {
    expect(toPaginated(['a', 'b'], 41, { page: 3, limit: 20 })).toEqual({
      data: ['a', 'b'],
      meta: { page: 3, limit: 20, total: 41, totalPages: 3 },
    });
  });

  it('reports zero pages for an empty result', () => {
    expect(toPaginated([], 0, { page: 1, limit: 20 }).meta.totalPages).toBe(0);
  });
});
