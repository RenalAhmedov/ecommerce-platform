import { Field, Int, ObjectType } from '@nestjs/graphql';

@ObjectType('ProductCategory')
export class ProductCategoryDto {
  @Field(() => Int)
  id: number;

  @Field()
  name: string;
}
