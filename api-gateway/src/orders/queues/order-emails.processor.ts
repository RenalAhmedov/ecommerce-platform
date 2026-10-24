import { OnWorkerEvent, Processor, WorkerHost } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import type { Job } from 'bullmq';
import { ThrottledWarning } from '../../common/logging/throttled-warning.js';
import { describeError } from '../../common/utils/describe-error.js';
import { OrderConfirmationService } from '../services/order-confirmation.service.js';
import type { OrderConfirmationJobData } from './interfaces/order-confirmation-job.interface.js';
import { ORDER_EMAILS_QUEUE } from './order-emails.constants.js';

@Processor(ORDER_EMAILS_QUEUE)
export class OrderEmailsProcessor extends WorkerHost {
  private readonly logger = new Logger(OrderEmailsProcessor.name);
  private readonly connectionWarning = new ThrottledWarning(this.logger);

  constructor(
    private readonly orderConfirmationService: OrderConfirmationService,
  ) {
    super();
  }

  process(job: Job<OrderConfirmationJobData>): Promise<void> {
    return this.orderConfirmationService.send(job.data.orderId);
  }

  @OnWorkerEvent('failed')
  onFailed(job: Job<OrderConfirmationJobData> | undefined, error: Error): void {
    this.logger.warn(
      `Confirmation email for order ${job?.data.orderId} failed (attempt ${job?.attemptsMade}): ${error.message}`,
    );
  }

  @OnWorkerEvent('error')
  onError(error: Error): void {
    this.connectionWarning.warn(
      `Order email worker unavailable (${describeError(error)})`,
    );
  }
}
