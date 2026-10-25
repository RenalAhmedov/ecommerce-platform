export const API_KEY_HEADER = 'x-api-key';

export const WEBHOOK_EVENT_KEY_PREFIX = 'webhook:event';

// Senders retry for minutes at most; a day is a generous window to recognise a redelivery.
export const WEBHOOK_EVENT_TTL_SECONDS = 24 * 60 * 60;
