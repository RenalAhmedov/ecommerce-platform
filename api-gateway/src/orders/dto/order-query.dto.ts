import { ArgsType, Field } from '@nestjs/graphql';
import { Transform } from 'class-transformer';
import { IsEnum, IsOptional } from 'class-validator';
import { PaginationQueryDto } from '../../common/dto/pagination-query.dto.js';
import { absentIfNull } from '../../common/transformers/optional.transformers.js';
import { OrderStatus } from '../enums/order-status.enum.js';

@ArgsType()
export class OrderQueryDto extends PaginationQueryDto {
  @Field(() => OrderStatus, { nullable: true })
  @IsOptional()
  @Transform(absentIfNull)
  @IsEnum(OrderStatus)
  status?: OrderStatus;
}
