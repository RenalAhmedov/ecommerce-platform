import type { Logger } from '@nestjs/common';

export class ThrottledWarning {
  private lastLoggedAt = Number.NEGATIVE_INFINITY;

  constructor(
    private readonly logger: Logger,
    private readonly intervalMs = 60_000,
  ) {}

  warn(message: string): void {
    const now = Date.now();

    if (now - this.lastLoggedAt < this.intervalMs) {
      return;
    }

    this.lastLoggedAt = now;
    this.logger.warn(message);
  }
}
