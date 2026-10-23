import { OnWorkerEvent, Processor, WorkerHost } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import type { Job } from 'bullmq';
import { ThrottledWarning } from '../../common/logging/throttled-warning.js';
import { describeError } from '../../common/utils/describe-error.js';
import { MailService } from '../../mail/mail.service.js';
import { renderOrderConfirmation } from '../mail/order-confirmation.mail.js';
import { OrdersRepository } from '../orders.repository.js';
import type { OrderConfirmationJobData } from './interfaces/order-confirmation-job.interface.js';
import { ORDER_EMAILS_QUEUE } from './order-emails.constants.js';

@Processor(ORDER_EMAILS_QUEUE)
export class OrderEmailsProcessor extends WorkerHost {
  private readonly logger = new Logger(OrderEmailsProcessor.name);
  private readonly connectionWarning = new ThrottledWarning(this.logger);

  constructor(
    private readonly ordersRepository: OrdersRepository,
    private readonly mailService: MailService,
  ) {
    super();
  }

  async process(job: Job<OrderConfirmationJobData>): Promise<void> {
    const order = await this.ordersRepository.findForConfirmation(
      job.data.orderId,
    );

    if (!order) {
      this.logger.warn(
        `Order ${job.data.orderId} no longer exists, skipping its confirmation email`,
      );
      return;
    }

    await this.mailService.send({
      to: order.user.email,
      ...renderOrderConfirmation(order),
    });
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
