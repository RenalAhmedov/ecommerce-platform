import { Inject, Injectable, Logger } from '@nestjs/common';
import type { ClientProxy } from '@nestjs/microservices';
import { lastValueFrom, timeout } from 'rxjs';
import { describeError } from '../../common/utils/describe-error.js';
import type { OrderPaidEvent } from './interfaces/order-paid-event.interface.js';
import {
  EVENT_PUBLISH_TIMEOUT_MS,
  ORDER_EVENTS_CLIENT,
  ORDER_PAID_EVENT,
} from './order-events.constants.js';

@Injectable()
export class OrderEventsPublisher {
  private readonly logger = new Logger(OrderEventsPublisher.name);

  constructor(
    @Inject(ORDER_EVENTS_CLIENT) private readonly client: ClientProxy,
  ) {}

  async publishPaid(event: OrderPaidEvent): Promise<void> {
    try {
      await lastValueFrom(
        this.client
          .emit(ORDER_PAID_EVENT, event)
          .pipe(timeout(EVENT_PUBLISH_TIMEOUT_MS)),
        { defaultValue: undefined },
      );
    } catch (error) {
      this.logger.warn(
        `Could not publish ${ORDER_PAID_EVENT} for order ${event.orderNumber}: ${describeError(error)}`,
      );
    }
  }
}
