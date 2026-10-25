// Versioned: a changed mapping gets a new index, which fills itself on the next sync.
export const PRODUCT_SEARCH_INDEX = 'products_v2';

export const PRODUCT_SEARCH_INDEX_PATTERN = 'products_v*';

export const PRODUCT_SEARCH_QUEUE = 'product-search';
export const SYNC_PRODUCT_SEARCH_JOB = 'sync-products';

// Safety net for changes whose trigger got lost (e.g. a webhook while Redis was down).
export const PRODUCT_SEARCH_SYNC_INTERVAL_MS = 5 * 60_000;

// Re-reads a little before the last sync, for writes that committed while it ran.
export const PRODUCT_SEARCH_SYNC_OVERLAP_MS = 60_000;

export const PRODUCT_SEARCH_BATCH_SIZE = 500;
