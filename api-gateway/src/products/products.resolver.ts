import { Args, Int, Query, Resolver } from '@nestjs/graphql';
import { Public } from '../auth/decorators/public.decorator.js';
import { ParseIdPipe } from '../common/pipes/parse-id.pipe.js';
import { ProductPageDto } from './dto/product-page.dto.js';
import { ProductQueryDto } from './dto/product-query.dto.js';
import { ProductResponseDto } from './dto/product-response.dto.js';
import { ProductsReadService } from './services/products-read.service.js';

@Resolver(() => ProductResponseDto)
export class ProductsResolver {
  constructor(private readonly productsReadService: ProductsReadService) {}

  @Public()
  @Query(() => ProductPageDto, {
    description:
      'Active products: the same filters, sorting and Redis cache as GET /api/products.',
  })
  products(@Args() filters: ProductQueryDto): Promise<ProductPageDto> {
    return this.productsReadService.findAll(filters);
  }

  @Public()
  @Query(() => ProductResponseDto, { description: 'An active product.' })
  product(
    @Args('id', { type: () => Int }, ParseIdPipe) id: number,
  ): Promise<ProductResponseDto> {
    return this.productsReadService.findOne(id);
  }
}
