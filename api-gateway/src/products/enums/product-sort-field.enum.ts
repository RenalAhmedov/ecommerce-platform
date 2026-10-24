import { registerEnumType } from '@nestjs/graphql';

export enum ProductSortField {
  CreatedAt = 'createdAt',
  Price = 'price',
  Name = 'name',
}

registerEnumType(ProductSortField, { name: 'ProductSortField' });
