interface QueryRunnerLike {
  query(sql: string, parameters?: unknown[]): Promise<unknown>;
}

export const advisoryLockKeys = {
  productSku: (sku: string) => `products.sku:${sku}`,
  productSlug: (slug: string) => `products.slug:${slug}`,
  userEmail: (email: string) => `users.email:${email.toLowerCase()}`,
};

export async function acquireAdvisoryLocks(
  runner: QueryRunnerLike,
  keys: string[],
): Promise<void> {
  for (const key of [...keys].sort()) {
    await runner.query('SELECT pg_advisory_xact_lock(hashtext($1))', [key]);
  }
}
