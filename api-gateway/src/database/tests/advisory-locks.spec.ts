import { acquireAdvisoryLocks, advisoryLockKeys } from '../advisory-locks.js';

describe('advisory locks', () => {
  it('builds the key formats Laravel must use as well', () => {
    expect(advisoryLockKeys.productSku('MK-001')).toBe('products.sku:MK-001');
    expect(advisoryLockKeys.productSlug('keyboard')).toBe(
      'products.slug:keyboard',
    );
    expect(advisoryLockKeys.userEmail('Ada@Example.TEST')).toBe(
      'users.email:ada@example.test',
    );
  });

  it('takes the locks in sorted order so two transactions cannot deadlock', async () => {
    const runner = { query: vi.fn() };

    await acquireAdvisoryLocks(runner, [
      'products.sku:B',
      'products.slug:a',
      'products.sku:A',
    ]);

    expect(runner.query.mock.calls.map(([, [key]]) => key as string)).toEqual([
      'products.sku:A',
      'products.sku:B',
      'products.slug:a',
    ]);
    expect(runner.query).toHaveBeenCalledWith(
      'SELECT pg_advisory_xact_lock(hashtext($1))',
      ['products.sku:A'],
    );
  });
});
