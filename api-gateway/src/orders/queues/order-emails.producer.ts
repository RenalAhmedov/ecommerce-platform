import { InjectQueue } from '@nestjs/bullmq';
import { Injectable, Logger } from '@nestjs/common';
import type { Queue } from 'bullmq';
import { ThrottledWarning } from '../../common/logging/throttled-warning.js';
import { describeError } from '../../common/utils/describe-error.js';
import type { OrderConfirmationJobData } from './interfaces/order-confirmation-job.interface.js';
import {
  ORDER_CONFIRMATION_JOB,
  ORDER_EMAIL_JOB_OPTIONS,
  ORDER_EMAILS_QUEUE,
} from './order-emails.constants.js';

@Injectable()
export class OrderEmailsProducer {
  private readonly logger = new Logger(OrderEmailsProducer.name);

  constructor(
    @InjectQueue(ORDER_EMAILS_QUEUE)
    private readonly queue: Queue<OrderConfirmationJobData>,
  ) {
    const connectionWarning = new ThrottledWarning(this.logger);

    this.queue.on('error', (error) =>
      connectionWarning.warn(
        `Order email queue unavailable (${describeError(error)})`,
      ),
    );
  }

  queueConfirmation(orderId: number): Promise<void> {
    return this.queueConfirmations([orderId]);
  }

  async queueConfirmations(orderIds: number[]): Promise<void> {
    if (orderIds.length === 0) {
      return;
    }

    try {
      await this.queue.addBulk(
        orderIds.map((orderId) => ({
          name: ORDER_CONFIRMATION_JOB,
          data: { orderId },
          opts: {
            ...ORDER_EMAIL_JOB_OPTIONS,
            jobId: `order-confirmation-${orderId}`,
          },
        })),
      );
    } catch (error) {
      this.logger.error(
        `Could not queue confirmation emails for order(s) ${orderIds.join(', ')}: ${describeError(error)}`,
      );
    }
  }
}
