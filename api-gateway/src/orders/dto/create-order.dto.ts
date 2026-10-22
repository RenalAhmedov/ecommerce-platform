import { Type } from 'class-transformer';
import {
  ArrayMaxSize,
  ArrayMinSize,
  ArrayUnique,
  IsArray,
  IsDefined,
  IsNotEmpty,
  IsString,
  MaxLength,
  ValidateNested,
} from 'class-validator';
import { OrderItemDto } from './order-item.dto.js';
import { ShippingAddressDto } from './shipping-address.dto.js';

export class CreateOrderDto {
  @IsArray()
  @ArrayMinSize(1)
  @ArrayMaxSize(50)
  @ArrayUnique((item: OrderItemDto | null) => item?.productId, {
    message: 'each product may only appear once in items',
  })
  @ValidateNested({ each: true })
  @Type(() => OrderItemDto)
  items: OrderItemDto[];

  @IsDefined()
  @ValidateNested()
  @Type(() => ShippingAddressDto)
  shippingAddress: ShippingAddressDto;

  @IsString()
  @IsNotEmpty()
  @MaxLength(100)
  paymentToken: string;
}
