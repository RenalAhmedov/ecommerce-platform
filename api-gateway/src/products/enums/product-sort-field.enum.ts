import { registerEnumType } from '@nestjs/graphql';

export enum ProductSortField {
  CreatedAt = 'createdAt',
  Price = 'price',
  Name = 'name',
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
