import { ArgsType, Field, Float, Int } from '@nestjs/graphql';
import { ApiPropertyOptional } from '@nestjs/swagger';
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
import { absentIfNull } from '../../common/transformers/optional.transformers.js';
import { SortOrder } from '../../common/enums/sort-order.enum.js';
import {
  toUpperCase,
  trim,
} from '../../common/transformers/string.transformers.js';
import { ProductSortField } from '../enums/product-sort-field.enum.js';

@ArgsType()
export class ProductQueryDto extends PaginationQueryDto {
  @ApiPropertyOptional({
    description:
      'Typo-tolerant search in name, description and SKU (Elasticsearch; plain substring search on Postgres if it is down).',
  })
  @Field({ nullable: true })
  @IsOptional()
  @Transform(trim)
  @IsString()
  @MaxLength(100)
  search?: string;

  @Field(() => Int, { nullable: true })
  @IsOptional()
  @Transform(absentIfNull)
  @Type(() => Number)
  @IsInt()
  @Min(1)
  categoryId?: number;

  @Field(() => Float, { nullable: true })
  @IsOptional()
  @Transform(absentIfNull)
  @Type(() => Number)
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0)
  minPrice?: number;

  @Field(() => Float, { nullable: true })
  @IsOptional()
  @Transform(absentIfNull)
  @Type(() => Number)
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0)
  maxPrice?: number;

  @ApiPropertyOptional({
    enum: ProductSortField,
    default: ProductSortField.CreatedAt,
    description:
      '`relevance` puts the best match first (with `search`); without a search term it means newest first.',
  })
  @Field(() => ProductSortField, { defaultValue: ProductSortField.CreatedAt })
  @IsOptional()
  @IsEnum(ProductSortField)
  sortBy: ProductSortField = ProductSortField.CreatedAt;

  @ApiPropertyOptional({ enum: SortOrder, default: SortOrder.Desc })
  @Field(() => SortOrder, { defaultValue: SortOrder.Desc })
  @IsOptional()
  @Transform(toUpperCase)
  @IsEnum(SortOrder)
  sortOrder: SortOrder = SortOrder.Desc;
}
