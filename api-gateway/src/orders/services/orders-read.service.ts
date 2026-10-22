import { Injectable, NotFoundException } from '@nestjs/common';
import type { JwtPayload } from '../../auth/interfaces/jwt-payload.interface.js';
import type { Paginated } from '../../common/interfaces/paginated.interface.js';
import { toPaginated } from '../../common/utils/paginate.js';
import { UserRole } from '../../users/enums/user-role.enum.js';
import type { OrderQueryDto } from '../dto/order-query.dto.js';
import { OrderResponseDto } from '../dto/order-response.dto.js';
import { OrderSummaryResponseDto } from '../dto/order-summary-response.dto.js';
import type { Order } from '../entities/order.entity.js';
import { OrdersRepository } from '../orders.repository.js';

@Injectable()
export class OrdersReadService {
  constructor(private readonly ordersRepository: OrdersRepository) {}

  async findAllForUser(
    userId: number,
    filters: OrderQueryDto,
  ): Promise<Paginated<OrderSummaryResponseDto>> {
    const [orders, total] = await this.ordersRepository.findPaginatedByUser(
      userId,
      filters,
    );

    return toPaginated(
      orders.map((order) => OrderSummaryResponseDto.fromEntity(order)),
      total,
      filters,
    );
  }

  async findOneForUser(
    id: number,
    user: JwtPayload,
  ): Promise<OrderResponseDto> {
    const order = await this.ordersRepository.findWithItemsById(id);

    if (!order || !this.canView(order, user)) {
      throw new NotFoundException('Order not found');
    }

    return OrderResponseDto.fromEntity(order);
  }

  private canView(order: Order, user: JwtPayload): boolean {
    return order.userId === user.sub || user.role === UserRole.Admin;
  }
}
