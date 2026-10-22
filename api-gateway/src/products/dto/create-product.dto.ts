import { Transform } from 'class-transformer';
import {
  IsBoolean,
  IsInt,
  IsNotEmpty,
  IsNumber,
  IsOptional,
  IsString,
  Matches,
  Max,
  MaxLength,
  Min,
  ValidateIf,
} from 'class-validator';
import {
  toUpperCase,
  trim,
} from '../../common/transformers/string.transformers.js';
import { SLUG_PATTERN } from '../../common/utils/slugify.js';

const isProvided = (_object: object, value: unknown) => value !== undefined;

export class CreateProductDto {
  @Transform(trim)
  @IsString()
  @IsNotEmpty()
  @MaxLength(255)
  name: string;

  @ValidateIf(isProvided)
  @Transform(trim)
  @IsString()
  @Matches(SLUG_PATTERN, {
    message:
      'slug may only contain lowercase letters, numbers and single hyphens',
  })
  @MaxLength(255)
  slug?: string;

  @Transform(toUpperCase)
  @IsString()
  @Matches(/^[A-Z0-9][A-Z0-9_-]*$/, {
    message: 'sku may only contain letters, numbers, hyphens and underscores',
  })
  @MaxLength(64)
  sku: string;

  @IsOptional()
  @IsString()
  @MaxLength(5000)
  description?: string | null;

  @IsNumber({ maxDecimalPlaces: 2, allowNaN: false, allowInfinity: false })
  @Min(0)
  @Max(9_999_999_999.99)
  price: number;

  @IsOptional()
  @IsInt()
  @Min(1)
  categoryId?: number | null;

  @ValidateIf(isProvided)
  @IsBoolean()
  isActive?: boolean;

  @ValidateIf(isProvided)
  @IsInt()
  @Min(0)
  @Max(1_000_000)
  initialStock?: number;
}
