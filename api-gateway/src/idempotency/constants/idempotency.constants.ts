export const IDEMPOTENCY_HEADER = 'idempotency-key';
export const IDEMPOTENCY_REPLAYED_HEADER = 'idempotent-replayed';
export const IDEMPOTENCY_KEY_PATTERN = /^[A-Za-z0-9_\-:.]{1,255}$/;
export const PROCESSING_TTL_SECONDS = 300;
export const RESULT_TTL_SECONDS = 86_400;
