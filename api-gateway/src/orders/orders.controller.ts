import { Body, Controller, Get, Param, Post, Query, Req } from '@nestjs/common';
import type { AuthenticatedRequest } from '../auth/interfaces/authenticated-request.interface.js';
import type { Paginated } from '../common/interfaces/paginated.interface.js';
import { ParseIdPipe } from '../common/pipes/parse-id.pipe.js';
import { CreateOrderDto } from './dto/create-order.dto.js';
import { OrderQueryDto } from './dto/order-query.dto.js';
import type { OrderResponseDto } from './dto/order-response.dto.js';
import type { OrderSummaryResponseDto } from './dto/order-summary-response.dto.js';
import { OrdersReadService } from './services/orders-read.service.js';
import { OrdersWriteService } from './services/orders-write.service.js';

@Controller('orders')
export class OrdersController {
  constructor(
    private readonly ordersReadService: OrdersReadService,
    private readonly ordersWriteService: OrdersWriteService,
  ) {}

  @Post()
  create(
    @Body() dto: CreateOrderDto,
    @Req() req: AuthenticatedRequest,
  ): Promise<OrderResponseDto> {
    return this.ordersWriteService.create(req.user.sub, dto);
  }

  @Get()
  findAll(
    @Query() filters: OrderQueryDto,
    @Req() req: AuthenticatedRequest,
  ): Promise<Paginated<OrderSummaryResponseDto>> {
    return this.ordersReadService.findAllForUser(req.user.sub, filters);
  }

  @Get(':id')
  findOne(
    @Param('id', ParseIdPipe) id: number,
    @Req() req: AuthenticatedRequest,
  ): Promise<OrderResponseDto> {
    return this.ordersReadService.findOneForUser(id, req.user);
  }
}
