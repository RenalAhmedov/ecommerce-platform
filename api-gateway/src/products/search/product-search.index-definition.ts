import type { estypes } from '@elastic/elasticsearch';

export const PRODUCT_SEARCH_SETTINGS: estypes.IndicesIndexSettings = {
  number_of_shards: 1,
  // A single node can't hold replicas; with one, the index would stay "yellow".
  number_of_replicas: 0,
  analysis: {
    normalizer: {
      lowercase: { type: 'custom', filter: ['lowercase'] },
    },
  },
};

export const PRODUCT_SEARCH_MAPPINGS: estypes.MappingTypeMapping = {
  dynamic: 'strict',
  properties: {
    id: { type: 'long' },
    // Matches whole words, typos ("headphnes") and the start of a word as you type ("headph").
    name: { type: 'search_as_you_type' },
    sku: { type: 'keyword', normalizer: 'lowercase' },
    description: { type: 'text' },
    categoryId: { type: 'long' },
    price: { type: 'scaled_float', scaling_factor: 100 },
    createdAt: { type: 'date' },
  },
};
