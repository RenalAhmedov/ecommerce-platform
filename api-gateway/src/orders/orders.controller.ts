import {
  Body,
  Controller,
  Get,
  HttpStatus,
  Param,
  Post,
  Query,
  Req,
  UseInterceptors,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiHeader,
  ApiOperation,
  ApiTags,
} from '@nestjs/swagger';
import { Throttle } from '@nestjs/throttler';
import type { AuthenticatedRequest } from '../auth/interfaces/authenticated-request.interface.js';
import type { Paginated } from '../common/interfaces/paginated.interface.js';
import { ParseIdPipe } from '../common/pipes/parse-id.pipe.js';
import { ApiErrorResponses } from '../common/swagger/api-error-responses.decorator.js';
import { ApiPaginatedResponse } from '../common/swagger/api-paginated-response.decorator.js';
import { IdempotencyInterceptor } from '../idempotency/idempotency.interceptor.js';
import { RATE_LIMITS } from '../rate-limit/constants/rate-limit.constants.js';
import { CreateOrderDto } from './dto/create-order.dto.js';
import { OrderQueryDto } from './dto/order-query.dto.js';
import type { OrderResponseDto } from './dto/order-response.dto.js';
import { OrderSummaryResponseDto } from './dto/order-summary-response.dto.js';
import { OrdersReadService } from './services/orders-read.service.js';
import { OrdersWriteService } from './services/orders-write.service.js';

@ApiTags('Orders')
@ApiBearerAuth()
@Controller('orders')
export class OrdersController {
  constructor(
    private readonly ordersReadService: OrdersReadService,
    private readonly ordersWriteService: OrdersWriteService,
  ) {}

  @Post()
  @Throttle({ default: RATE_LIMITS.placeOrder })
  @UseInterceptors(IdempotencyInterceptor)
  @ApiOperation({
    summary: 'Place an order',
    description:
      'Reserves stock, charges the (mock) payment and queues a confirmation email. ' +
      'Prices come from the database. paymentToken "tok_declined" simulates a declined card.',
  })
  @ApiHeader({
    name: 'Idempotency-Key',
    required: false,
    description:
      'Unique per checkout attempt (e.g. a UUID). Retries with the same key return the original response.',
  })
  @ApiErrorResponses(
    HttpStatus.BAD_REQUEST,
    HttpStatus.UNAUTHORIZED,
    HttpStatus.PAYMENT_REQUIRED,
    HttpStatus.CONFLICT,
    HttpStatus.UNPROCESSABLE_ENTITY,
    HttpStatus.TOO_MANY_REQUESTS,
    HttpStatus.SERVICE_UNAVAILABLE,
  )
  create(
    @Body() dto: CreateOrderDto,
    @Req() req: AuthenticatedRequest,
  ): Promise<OrderResponseDto> {
    return this.ordersWriteService.create(req.user.sub, dto);
  }

  @Get()
  @ApiOperation({ summary: "List the logged-in user's orders" })
  @ApiPaginatedResponse(OrderSummaryResponseDto)
  @ApiErrorResponses(HttpStatus.BAD_REQUEST, HttpStatus.UNAUTHORIZED)
  findAll(
    @Query() filters: OrderQueryDto,
    @Req() req: AuthenticatedRequest,
  ): Promise<Paginated<OrderSummaryResponseDto>> {
    return this.ordersReadService.findAllForUser(req.user.sub, filters);
  }

  @Get(':id')
  @ApiOperation({
    summary: 'Get an order with its items',
    description: 'Customers see only their own orders; admins see any order.',
  })
  @ApiErrorResponses(
    HttpStatus.BAD_REQUEST,
    HttpStatus.UNAUTHORIZED,
    HttpStatus.NOT_FOUND,
  )
  findOne(
    @Param('id', ParseIdPipe) id: number,
    @Req() req: AuthenticatedRequest,
  ): Promise<OrderResponseDto> {
    return this.ordersReadService.findOneForUser(id, req.user);
  }
}
