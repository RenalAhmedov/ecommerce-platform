import { randomBytes } from 'node:crypto';

export function generateOrderNumber(now = new Date()): string {
  const date = now.toISOString().slice(0, 10).replaceAll('-', '');

  return `ORD-${date}-${randomBytes(4).toString('hex').toUpperCase()}`;
}
