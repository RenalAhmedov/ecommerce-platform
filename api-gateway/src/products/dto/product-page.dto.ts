import { Field, ObjectType } from '@nestjs/graphql';
import { PaginationMetaDto } from '../../common/dto/pagination-meta.dto.js';
import type { Paginated } from '../../common/interfaces/paginated.interface.js';
import { ProductResponseDto } from './product-response.dto.js';

/** GraphQL has no generics, so each paginated list gets its own type. */
@ObjectType('ProductPage')
export class ProductPageDto implements Paginated<ProductResponseDto> {
  @Field(() => [ProductResponseDto])
  data: ProductResponseDto[];

  @Field(() => PaginationMetaDto)
  meta: PaginationMetaDto;
}
