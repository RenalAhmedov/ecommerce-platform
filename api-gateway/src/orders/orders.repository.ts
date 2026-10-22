import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { type EntityManager, Repository } from 'typeorm';
import type { OrderQueryDto } from './dto/order-query.dto.js';
import { OrderItem } from './entities/order-item.entity.js';
import { Order } from './entities/order.entity.js';
import { OrderStatus } from './enums/order-status.enum.js';
import type { NewOrder, NewOrderItem } from './types/order.types.js';

@Injectable()
export class OrdersRepository {
  constructor(
    @InjectRepository(Order)
    private readonly orders: Repository<Order>,
    @InjectRepository(OrderItem)
    private readonly items: Repository<OrderItem>,
  ) {}

  withManager(manager: EntityManager): OrdersRepository {
    return new OrdersRepository(
      manager.getRepository(Order),
      manager.getRepository(OrderItem),
    );
  }

  transaction<T>(
    work: (orders: OrdersRepository, manager: EntityManager) => Promise<T>,
  ): Promise<T> {
    return this.orders.manager.transaction((manager) =>
      work(this.withManager(manager), manager),
    );
  }

  findPaginatedByUser(
    userId: number,
    filters: OrderQueryDto,
  ): Promise<[Order[], number]> {
    const ordersQuery = this.orders
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
    return this.createWithItemsQuery(id).getOne();
  }

  getWithItemsById(id: number): Promise<Order> {
    return this.createWithItemsQuery(id).getOneOrFail();
  }

  findForConfirmation(id: number): Promise<Order | null> {
    return this.createWithItemsQuery(id)
      .leftJoin('order.user', 'user')
      .addSelect(['user.id', 'user.name', 'user.email'])
      .getOne();
  }

  findStalePending(createdBefore: Date, limit: number): Promise<Order[]> {
    return this.orders
      .createQueryBuilder('order')
      .leftJoinAndSelect('order.items', 'item')
      .where('order.status = :status', { status: OrderStatus.Pending })
      .andWhere('order.createdAt < :createdBefore', { createdBefore })
      .orderBy('order.createdAt', 'ASC')
      .take(limit)
      .getMany();
  }

  async findIdsPaidSince(paidSince: Date): Promise<number[]> {
    const orders = await this.orders
      .createQueryBuilder('order')
      .select('order.id')
      .where('order.paidAt >= :paidSince', { paidSince })
      .getMany();

    return orders.map((order) => order.id);
  }

  async createWithItems(data: NewOrder, items: NewOrderItem[]): Promise<Order> {
    const order = await this.orders.save(this.orders.create(data));

    await this.items.insert(
      items.map((item) => ({ ...item, orderId: order.id })),
    );

    return order;
  }

  async lockStatusById(id: number): Promise<OrderStatus | null> {
    const order = await this.orders
      .createQueryBuilder('order')
      .select(['order.id', 'order.status'])
      .where('order.id = :id', { id })
      .setLock('pessimistic_write')
      .getOne();

    return order?.status ?? null;
  }

  async markPaid(id: number, paymentReference: string): Promise<void> {
    await this.orders.update(id, {
      status: OrderStatus.Paid,
      paymentReference,
      paidAt: () => 'NOW()',
    });
  }

  async updateStatus(id: number, status: OrderStatus): Promise<void> {
    await this.orders.update(id, { status });
  }

  private createWithItemsQuery(id: number) {
    return this.orders
      .createQueryBuilder('order')
      .leftJoinAndSelect('order.items', 'item')
      .where('order.id = :id', { id })
      .orderBy('item.id', 'ASC');
  }
}
