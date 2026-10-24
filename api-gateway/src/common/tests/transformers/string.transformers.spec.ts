import type { TransformFnParams } from 'class-transformer';
import {
  normalizeEmail,
  toUpperCase,
  trim,
} from '../../transformers/string.transformers.js';

const params = (value: unknown) => ({ value }) as TransformFnParams;

describe('string transformers', () => {
  it('trims, upper-cases and normalises emails', () => {
    expect(trim(params('  Mug  '))).toBe('Mug');
    expect(toUpperCase(params(' mug-1 '))).toBe('MUG-1');
    expect(normalizeEmail(params(' Ada@Example.COM '))).toBe('ada@example.com');
  });

  it('passes non-strings through for the validators to reject', () => {
    for (const transform of [trim, toUpperCase, normalizeEmail]) {
      expect(transform(params(42))).toBe(42);
      expect(transform(params(undefined))).toBeUndefined();
    }
  });
});
