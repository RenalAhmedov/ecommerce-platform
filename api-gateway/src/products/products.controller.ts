import {
  Body,
  Controller,
  Get,
  HttpStatus,
  Param,
  Post,
  Put,
  Query,
  Req,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { Public } from '../auth/decorators/public.decorator.js';
import { Roles } from '../auth/decorators/roles.decorator.js';
import type { AuthenticatedRequest } from '../auth/interfaces/authenticated-request.interface.js';
import type { Paginated } from '../common/interfaces/paginated.interface.js';
import { ParseIdPipe } from '../common/pipes/parse-id.pipe.js';
import { ApiErrorResponses } from '../common/swagger/api-error-responses.decorator.js';
import { ApiPaginatedResponse } from '../common/swagger/api-paginated-response.decorator.js';
import { UserRole } from '../users/enums/user-role.enum.js';
import { CreateProductDto } from './dto/create-product.dto.js';
import { ProductQueryDto } from './dto/product-query.dto.js';
import { ProductResponseDto } from './dto/product-response.dto.js';
import { UpdateProductDto } from './dto/update-product.dto.js';
import { ProductsReadService } from './services/products-read.service.js';
import { ProductsWriteService } from './services/products-write.service.js';

@ApiTags('Products')
@Controller('products')
export class ProductsController {
  constructor(
    private readonly productsReadService: ProductsReadService,
    private readonly productsWriteService: ProductsWriteService,
  ) {}

  @Public()
  @Get()
  @ApiOperation({
    summary: 'List active products',
    description: 'Paginated, filterable and sortable. Cached in Redis.',
  })
  @ApiPaginatedResponse(ProductResponseDto)
  @ApiErrorResponses(HttpStatus.BAD_REQUEST)
  findAll(
    @Query() filters: ProductQueryDto,
  ): Promise<Paginated<ProductResponseDto>> {
    return this.productsReadService.findAll(filters);
  }

  @Public()
  @Get(':id')
  @ApiOperation({ summary: 'Get an active product (cached in Redis)' })
  @ApiErrorResponses(HttpStatus.BAD_REQUEST, HttpStatus.NOT_FOUND)
  findOne(@Param('id', ParseIdPipe) id: number): Promise<ProductResponseDto> {
    return this.productsReadService.findOne(id);
  }

  @Roles(UserRole.Admin)
  @Post()
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Create a product with its initial stock (admin)' })
  @ApiErrorResponses(
    HttpStatus.BAD_REQUEST,
    HttpStatus.UNAUTHORIZED,
    HttpStatus.FORBIDDEN,
    HttpStatus.CONFLICT,
  )
  create(
    @Body() dto: CreateProductDto,
    @Req() req: AuthenticatedRequest,
  ): Promise<ProductResponseDto> {
    return this.productsWriteService.create(dto, req.user.sub);
  }

  @Roles(UserRole.Admin)
  @Put(':id')
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Update a product (admin)' })
  @ApiErrorResponses(
    HttpStatus.BAD_REQUEST,
    HttpStatus.UNAUTHORIZED,
    HttpStatus.FORBIDDEN,
    HttpStatus.NOT_FOUND,
    HttpStatus.CONFLICT,
  )
  update(
    @Param('id', ParseIdPipe) id: number,
    @Body() dto: UpdateProductDto,
    @Req() req: AuthenticatedRequest,
  ): Promise<ProductResponseDto> {
    return this.productsWriteService.update(id, dto, req.user.sub);
  }
}
