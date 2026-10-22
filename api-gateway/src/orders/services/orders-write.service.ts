import { HttpException, HttpStatus, Injectable } from '@nestjs/common';
import { ProductsCache } from '../../products/products.cache.js';
import type { CreateOrderDto } from '../dto/create-order.dto.js';
import { OrderResponseDto } from '../dto/order-response.dto.js';
import { OrdersRepository } from '../orders.repository.js';
import { OrderPaymentService } from './order-payment.service.js';
import { OrderPlacementService } from './order-placement.service.js';

@Injectable()
export class OrdersWriteService {
  constructor(
    private readonly orderPlacementService: OrderPlacementService,
    private readonly orderPaymentService: OrderPaymentService,
    private readonly ordersRepository: OrdersRepository,
    private readonly productsCache: ProductsCache,
  ) {}

  async create(userId: number, dto: CreateOrderDto): Promise<OrderResponseDto> {
    const order = await this.orderPlacementService.place(userId, dto);

    let isPaid: boolean;
    try {
      isPaid = await this.orderPaymentService.settle(order, dto.paymentToken);
    } finally {
      await this.productsCache.invalidate();
    }

    if (!isPaid) {
      throw new HttpException(
        `Payment was declined for order ${order.orderNumber}`,
        HttpStatus.PAYMENT_REQUIRED,
      );
    }

    return OrderResponseDto.fromEntity(
      await this.ordersRepository.getWithItemsById(order.id),
    );
  }
}
