import { Field, ObjectType } from '@nestjs/graphql';
import { PaginationMetaDto } from '../../common/dto/pagination-meta.dto.js';
import type { Paginated } from '../../common/interfaces/paginated.interface.js';
import { OrderSummaryResponseDto } from './order-summary-response.dto.js';

@ObjectType('OrderPage')
export class OrderPageDto implements Paginated<OrderSummaryResponseDto> {
  @Field(() => [OrderSummaryResponseDto])
  data: OrderSummaryResponseDto[];

  @Field(() => PaginationMetaDto)
  meta: PaginationMetaDto;
}
