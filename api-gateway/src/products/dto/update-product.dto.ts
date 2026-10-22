import { OmitType, PartialType } from '@nestjs/mapped-types';
import { CreateProductDto } from './create-product.dto.js';

export class UpdateProductDto extends PartialType(
  OmitType(CreateProductDto, ['initialStock'] as const),
  { skipNullProperties: false },
) {}
