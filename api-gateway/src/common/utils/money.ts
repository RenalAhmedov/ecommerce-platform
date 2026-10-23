export function toMoney(amount: number): string {
  return amount.toFixed(2);
}

export function toCents(amount: string): number {
  return Math.round(Number(amount) * 100);
}

export function fromCents(cents: number): string {
  return (cents / 100).toFixed(2);
}
