export function describeError(error: unknown): string {
  if (!(error instanceof Error)) {
    return String(error);
  }

  const { code } = error as Error & { code?: string };

  return code ?? (error.message || error.name);
}
