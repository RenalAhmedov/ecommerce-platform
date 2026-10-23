import { generateOrderNumber } from '../../utils/order-number.js';

describe('generateOrderNumber', () => {
  it('combines the UTC date with a random suffix', () => {
    expect(generateOrderNumber(new Date('2026-10-23T23:30:00Z'))).toMatch(
      /^ORD-20261023-[0-9A-F]{8}$/,
    );
  });

  it('does not repeat', () => {
    const numbers = new Set(
      Array.from({ length: 1_000 }, () => generateOrderNumber()),
    );

    expect(numbers.size).toBe(1_000);
  });
});
