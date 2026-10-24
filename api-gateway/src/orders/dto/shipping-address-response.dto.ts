import { Field, ObjectType } from '@nestjs/graphql';
import type { ShippingAddress } from '../interfaces/shipping-address.interface.js';

@ObjectType('ShippingAddress')
export class ShippingAddressResponseDto implements ShippingAddress {
  @Field()
  fullName: string;

  @Field()
  line1: string;

  @Field({ nullable: true })
  line2?: string;

  @Field()
  city: string;

  @Field()
  postalCode: string;

  @Field({ description: 'ISO 3166-1 alpha-2 country code' })
  country: string;
}
