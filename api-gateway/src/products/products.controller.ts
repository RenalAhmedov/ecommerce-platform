import {
  Body,
  Controller,
  Get,
  Param,
  Post,
  Put,
  Query,
  Req,
} from '@nestjs/common';
import { Public } from '../auth/decorators/public.decorator.js';
import { Roles } from '../auth/decorators/roles.decorator.js';
import type { AuthenticatedRequest } from '../auth/interfaces/authenticated-request.interface.js';
import type { Paginated } from '../common/interfaces/paginated.interface.js';
import { ParseIdPipe } from '../common/pipes/parse-id.pipe.js';
import { UserRole } from '../users/enums/user-role.enum.js';
import { CreateProductDto } from './dto/create-product.dto.js';
import { ProductQueryDto } from './dto/product-query.dto.js';
import type { ProductResponseDto } from './dto/product-response.dto.js';
import { UpdateProductDto } from './dto/update-product.dto.js';
import { ProductsService } from './products.service.js';

@Controller('products')
export class ProductsController {
  constructor(private readonly productsService: ProductsService) {}

  @Public()
  @Get()
  findAll(
    @Query() filters: ProductQueryDto,
  ): Promise<Paginated<ProductResponseDto>> {
    return this.productsService.findAll(filters);
  }

  @Public()
  @Get(':id')
  findOne(@Param('id', ParseIdPipe) id: number): Promise<ProductResponseDto> {
    return this.productsService.findOne(id);
  }

  @Roles(UserRole.Admin)
  @Post()
  create(
    @Body() dto: CreateProductDto,
    @Req() req: AuthenticatedRequest,
  ): Promise<ProductResponseDto> {
    return this.productsService.create(dto, req.user.sub);
  }

  @Roles(UserRole.Admin)
  @Put(':id')
  update(
    @Param('id', ParseIdPipe) id: number,
    @Body() dto: UpdateProductDto,
  ): Promise<ProductResponseDto> {
    return this.productsService.update(id, dto);
  }
}
