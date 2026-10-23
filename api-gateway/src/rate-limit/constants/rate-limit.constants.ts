const ONE_MINUTE_MS = 60_000;

export const RATE_LIMITS = {
  default: { limit: 100, ttl: ONE_MINUTE_MS },
  login: { limit: 5, ttl: ONE_MINUTE_MS },
  register: { limit: 3, ttl: ONE_MINUTE_MS },
  placeOrder: { limit: 10, ttl: ONE_MINUTE_MS },
};

export const RATE_LIMIT_KEY_PREFIX = 'rate-limit';
