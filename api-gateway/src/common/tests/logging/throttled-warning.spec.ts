import type { Logger } from '@nestjs/common';
import { ThrottledWarning } from '../../logging/throttled-warning.js';

describe('ThrottledWarning', () => {
  const logger = { warn: vi.fn() };

  beforeEach(() => {
    vi.useFakeTimers({ now: 0 });
    logger.warn.mockReset();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('logs a repeated warning at most once per interval', () => {
    const warning = new ThrottledWarning(logger as unknown as Logger, 60_000);

    warning.warn('Redis unavailable');
    vi.advanceTimersByTime(59_999);
    warning.warn('Redis unavailable');
    vi.advanceTimersByTime(1);
    warning.warn('Redis unavailable');

    expect(logger.warn).toHaveBeenCalledTimes(2);
  });
});
