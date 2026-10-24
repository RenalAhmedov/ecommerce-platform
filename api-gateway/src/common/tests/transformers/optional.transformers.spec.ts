import type { TransformFnParams } from 'class-transformer';
import { absentIfNull } from '../../transformers/optional.transformers.js';

describe('absentIfNull', () => {
  const params = (value: unknown) => ({ value }) as TransformFnParams;

  it('treats an explicit null like a filter that was left out', () => {
    expect(absentIfNull(params(null))).toBeUndefined();
    expect(absentIfNull(params(undefined))).toBeUndefined();
  });

  it('keeps every real value, including zero', () => {
    expect(absentIfNull(params(0))).toBe(0);
    expect(absentIfNull(params(3))).toBe(3);
  });
});
