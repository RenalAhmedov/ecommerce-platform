import { Args, Context, Int, Query, Resolver } from '@nestjs/graphql';
import type { AuthenticatedRequest } from '../auth/interfaces/authenticated-request.interface.js';
import { ParseIdPipe } from '../common/pipes/parse-id.pipe.js';
import { OrderPageDto } from './dto/order-page.dto.js';
import { OrderQueryDto } from './dto/order-query.dto.js';
import { OrderResponseDto } from './dto/order-response.dto.js';
import { OrdersReadService } from './services/orders-read.service.js';

// Read-only: orders are placed through REST, which has the Idempotency-Key handling.
@Resolver(() => OrderResponseDto)
export class OrdersResolver {
  constructor(private readonly ordersReadService: OrdersReadService) {}

  @Query(() => OrderPageDto, { description: "The logged-in user's orders." })
  myOrders(
    @Args() filters: OrderQueryDto,
    @Context('req') req: AuthenticatedRequest,
  ): Promise<OrderPageDto> {
    return this.ordersReadService.findAllForUser(req.user.sub, filters);
  }

  @Query(() => OrderResponseDto, {
    description: 'An order with its items: your own, or any order for admins.',
  })
  order(
    @Args('id', { type: () => Int }, ParseIdPipe) id: number,
    @Context('req') req: AuthenticatedRequest,
  ): Promise<OrderResponseDto> {
    return this.ordersReadService.findOneForUser(id, req.user);
  }
}
