import { Transform } from 'class-transformer';
import {
  IsISO31661Alpha2,
  IsNotEmpty,
  IsOptional,
  IsString,
  MaxLength,
} from 'class-validator';
import {
  toUpperCase,
  trim,
} from '../../common/transformers/string.transformers.js';
import type { ShippingAddress } from '../interfaces/shipping-address.interface.js';

export class ShippingAddressDto implements ShippingAddress {
  @Transform(trim)
  @IsString()
  @IsNotEmpty()
  @MaxLength(255)
  fullName: string;

  @Transform(trim)
  @IsString()
  @IsNotEmpty()
  @MaxLength(255)
  line1: string;

  @IsOptional()
  @Transform(trim)
  @IsString()
  @MaxLength(255)
  line2?: string;

  @Transform(trim)
  @IsString()
  @IsNotEmpty()
  @MaxLength(100)
  city: string;

  @Transform(trim)
  @IsString()
  @IsNotEmpty()
  @MaxLength(20)
  postalCode: string;

  @Transform(toUpperCase)
  @IsISO31661Alpha2()
  country: string;
}
