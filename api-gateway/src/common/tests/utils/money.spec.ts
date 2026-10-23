import { fromCents, toCents, toMoney } from '../../utils/money.js';

describe('money', () => {
  it('formats amounts with two decimals', () => {
    expect(toMoney(89.9)).toBe('89.90');
    expect(toMoney(5)).toBe('5.00');
  });

  it('converts to cents without floating point drift', () => {
    expect(toCents('0.10') * 3).toBe(30);
    expect(toCents('19.99')).toBe(1999);
    expect(toCents('9999999999.99')).toBe(999_999_999_999);
  });

  it('converts cents back to a two-decimal string', () => {
    expect(fromCents(30)).toBe('0.30');
    expect(fromCents(5_997)).toBe('59.97');
    expect(fromCents(999_999_999_999)).toBe('9999999999.99');
  });
});
