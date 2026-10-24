export const REQUEST_ID_HEADER = 'X-Request-Id';

/** Same rule as the admin service, so an id passes unchanged between them. */
export const REQUEST_ID_PATTERN = /^[A-Za-z0-9-]{8,64}$/;
