import { Controller, Get, Param, Query } from '@nestjs/common';
import { Public } from '../auth/decorators/public.decorator.js';
import type { Paginated } from '../common/interfaces/paginated.interface.js';
import { ParseIdPipe } from '../common/pipes/parse-id.pipe.js';
import { ProductQueryDto } from './dto/product-query.dto.js';
import type { ProductResponseDto } from './dto/product-response.dto.js';
import { ProductsService } from './products.service.js';

@Controller('products')
export class ProductsController {
  constructor(private readonly productsService: ProductsService) {}

  @Public()
  @Get()
  findAll(
    @Query() query: ProductQueryDto,
  ): Promise<Paginated<ProductResponseDto>> {
    return this.productsService.findAll(query);
  }

  @Public()
  @Get(':id')
  findOne(@Param('id', ParseIdPipe) id: number): Promise<ProductResponseDto> {
    return this.productsService.findOne(id);
  }
}
