import { DateTimeScalar } from '../date-time.scalar.js';

describe('DateTimeScalar', () => {
  it('serialises dates and the ISO strings that come back from the cache alike', () => {
    expect(DateTimeScalar.serialize(new Date('2026-10-24T09:54:33Z'))).toBe(
      '2026-10-24T09:54:33.000Z',
    );
    expect(DateTimeScalar.serialize('2026-10-24T09:54:33.000Z')).toBe(
      '2026-10-24T09:54:33.000Z',
    );
  });

  it('refuses values that are not dates', () => {
    expect(() => DateTimeScalar.serialize('yesterday')).toThrow(TypeError);
  });
});
