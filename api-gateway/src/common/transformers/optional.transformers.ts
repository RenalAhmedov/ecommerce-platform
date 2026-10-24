import type { TransformFnParams } from 'class-transformer';

// GraphQL clients send an unused filter as null where REST leaves it out; both mean "no filter".
export function absentIfNull({ value }: TransformFnParams): unknown {
  return value ?? undefined;
}
