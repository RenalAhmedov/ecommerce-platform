import type { TransformFnParams } from 'class-transformer';

/**
 * GraphQL clients may send an unused filter as an explicit null (REST query
 * strings just leave it out). Both mean "no filter", so null becomes undefined
 * before the rest of the code sees it.
 */
export function absentIfNull({ value }: TransformFnParams): unknown {
  return value ?? undefined;
}
