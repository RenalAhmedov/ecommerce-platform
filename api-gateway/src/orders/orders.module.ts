import { BullModule } from '@nestjs/bullmq';
import { Module } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { ClientsModule } from '@nestjs/microservices';
import { TypeOrmModule } from '@nestjs/typeorm';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { IdempotencyModule } from '../idempotency/idempotency.module.js';
import { InventoryModule } from '../inventory/inventory.module.js';
import { MailModule } from '../mail/mail.module.js';
import { RedisEventsClient } from '../messaging/redis-events.transport.js';
import { createRedisTransportOptions } from '../messaging/redis-transport.options.js';
import { PaymentsModule } from '../payments/payments.module.js';
import { ProductsModule } from '../products/products.module.js';
import { OrderItem } from './entities/order-item.entity.js';
import { Order } from './entities/order.entity.js';
import { ORDER_EVENTS_CLIENT } from './events/order-events.constants.js';
import { OrderEventsController } from './events/order-events.controller.js';
import { OrderEventsPublisher } from './events/order-events.publisher.js';
import { OrdersController } from './orders.controller.js';
import { OrdersRepository } from './orders.repository.js';
import { ORDER_EMAILS_QUEUE } from './queues/order-emails.constants.js';
import { OrderEmailsProcessor } from './queues/order-emails.processor.js';
import { OrderEmailsProducer } from './queues/order-emails.producer.js';
import { ORDER_MAINTENANCE_QUEUE } from './queues/order-maintenance.constants.js';
import { OrderMaintenanceProcessor } from './queues/order-maintenance.processor.js';
import { OrderMaintenanceScheduler } from './queues/order-maintenance.scheduler.js';
import { OrderConfirmationSweepService } from './services/order-confirmation-sweep.service.js';
import { OrderExpiryService } from './services/order-expiry.service.js';
import { OrderPaymentService } from './services/order-payment.service.js';
import { OrderPlacementService } from './services/order-placement.service.js';
import { OrderReleaseService } from './services/order-release.service.js';
import { OrdersReadService } from './services/orders-read.service.js';
import { OrdersWriteService } from './services/orders-write.service.js';
import { OrderValidator } from './validators/order.validator.js';

@Module({
  imports: [
    TypeOrmModule.forFeature([Order, OrderItem]),
    ProductsModule,
    InventoryModule,
    PaymentsModule,
    IdempotencyModule,
    MailModule,
    BullModule.registerQueue(
      { name: ORDER_EMAILS_QUEUE },
      { name: ORDER_MAINTENANCE_QUEUE },
    ),
    ClientsModule.registerAsync([
      {
        name: ORDER_EVENTS_CLIENT,
        inject: [ConfigService],
        useFactory: (config: ConfigService<EnvironmentVariables, true>) => ({
          customClass: RedisEventsClient,
          options: createRedisTransportOptions(config),
        }),
      },
    ]),
  ],
  controllers: [OrdersController, OrderEventsController],
  providers: [
    OrdersRepository,
    OrderValidator,
    OrdersReadService,
    OrderPlacementService,
    OrderReleaseService,
    OrderPaymentService,
    OrdersWriteService,
    OrderEmailsProducer,
    OrderEmailsProcessor,
    OrderEventsPublisher,
    OrderExpiryService,
    OrderConfirmationSweepService,
    OrderMaintenanceScheduler,
    OrderMaintenanceProcessor,
  ],
})
export class OrdersModule {}
