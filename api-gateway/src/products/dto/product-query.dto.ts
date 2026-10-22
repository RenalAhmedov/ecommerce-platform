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

export class ProductQueryDto extends PaginationQueryDto {
  @IsOptional()
  @Transform(trim)
  @IsString()
  @MaxLength(100)
  search?: string;

  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  categoryId?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0)
  minPrice?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0)
  maxPrice?: number;

  @IsOptional()
  @IsEnum(ProductSortField)
  sortBy: ProductSortField = ProductSortField.CreatedAt;

  @IsOptional()
  @Transform(toUpperCase)
  @IsEnum(SortOrder)
  sortOrder: SortOrder = SortOrder.Desc;
}
