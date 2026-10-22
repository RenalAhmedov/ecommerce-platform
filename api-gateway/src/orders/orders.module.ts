import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { InventoryModule } from '../inventory/inventory.module.js';
import { PaymentsModule } from '../payments/payments.module.js';
import { ProductsModule } from '../products/products.module.js';
import { OrderItem } from './entities/order-item.entity.js';
import { Order } from './entities/order.entity.js';
import { OrdersController } from './orders.controller.js';
import { OrdersRepository } from './orders.repository.js';
import { OrderPaymentService } from './services/order-payment.service.js';
import { OrderPlacementService } from './services/order-placement.service.js';
import { OrdersReadService } from './services/orders-read.service.js';
import { OrdersWriteService } from './services/orders-write.service.js';
import { OrderValidator } from './validators/order.validator.js';

@Module({
  imports: [
    TypeOrmModule.forFeature([Order, OrderItem]),
    ProductsModule,
    InventoryModule,
    PaymentsModule,
  ],
  controllers: [OrdersController],
  providers: [
    OrdersRepository,
    OrderValidator,
    OrdersReadService,
    OrderPlacementService,
    OrderPaymentService,
    OrdersWriteService,
  ],
})
export class OrdersModule {}
