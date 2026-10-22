import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import type { OrderQueryDto } from './dto/order-query.dto.js';
import { Order } from './entities/order.entity.js';

@Injectable()
export class OrdersRepository {
  constructor(
    @InjectRepository(Order)
    private readonly repository: Repository<Order>,
  ) {}

  findPaginatedByUser(
    userId: number,
    filters: OrderQueryDto,
  ): Promise<[Order[], number]> {
    const ordersQuery = this.repository
      .createQueryBuilder('order')
      .where('order.userId = :userId', { userId });

    if (filters.status !== undefined) {
      ordersQuery.andWhere('order.status = :status', {
        status: filters.status,
      });
    }

    return ordersQuery
      .orderBy('order.createdAt', 'DESC')
      .addOrderBy('order.id', 'DESC')
      .offset((filters.page - 1) * filters.limit)
      .limit(filters.limit)
      .getManyAndCount();
  }

  findWithItemsById(id: number): Promise<Order | null> {
    return this.repository
      .createQueryBuilder('order')
      .leftJoinAndSelect('order.items', 'item')
      .where('order.id = :id', { id })
      .orderBy('item.id', 'ASC')
      .getOne();
  }
}
