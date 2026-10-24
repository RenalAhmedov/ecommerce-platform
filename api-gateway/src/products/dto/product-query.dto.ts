import { ArgsType, Field, Float, Int } from '@nestjs/graphql';
import { Transform, Type } from 'class-transformer';
import {
  IsEnum,
  IsInt,
  IsNumber,
  IsOptional,
  IsString,
  MaxLength,
  Min,
} from 'class-validator';
import { PaginationQueryDto } from '../../common/dto/pagination-query.dto.js';
import { SortOrder } from '../../common/enums/sort-order.enum.js';
import {
  toUpperCase,
  trim,
} from '../../common/transformers/string.transformers.js';
import { ProductSortField } from '../enums/product-sort-field.enum.js';

@ArgsType()
export class ProductQueryDto extends PaginationQueryDto {
  @Field({ nullable: true })
  @IsOptional()
  @Transform(trim)
  @IsString()
  @MaxLength(100)
  search?: string;

  @Field(() => Int, { nullable: true })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  categoryId?: number;

  @Field(() => Float, { nullable: true })
  @IsOptional()
  @Type(() => Number)
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0)
  minPrice?: number;

  @Field(() => Float, { nullable: true })
  @IsOptional()
  @Type(() => Number)
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0)
  maxPrice?: number;

  @Field(() => ProductSortField, { defaultValue: ProductSortField.CreatedAt })
  @IsOptional()
  @IsEnum(ProductSortField)
  sortBy: ProductSortField = ProductSortField.CreatedAt;

  @Field(() => SortOrder, { defaultValue: SortOrder.Desc })
  @IsOptional()
  @Transform(toUpperCase)
  @IsEnum(SortOrder)
  sortOrder: SortOrder = SortOrder.Desc;
}
