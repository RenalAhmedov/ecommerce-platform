import { registerEnumType } from '@nestjs/graphql';

export enum ProductSortField {
  CreatedAt = 'createdAt',
  Price = 'price',
  Name = 'name',
  /** Best match first. Needs a search term; without one (or on the Postgres fallback) it means newest first. */
  Relevance = 'relevance',
}

registerEnumType(ProductSortField, {
  name: 'ProductSortField',
  valuesMap: {
    Relevance: {
      description:
        'Best match first (with a search term); otherwise newest first.',
    },
  },
});
