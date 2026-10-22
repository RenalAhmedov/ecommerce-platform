import { OnWorkerEvent, Processor, WorkerHost } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import type { Job } from 'bullmq';
import { ThrottledWarning } from '../../common/logging/throttled-warning.js';
import { describeError } from '../../common/utils/describe-error.js';
import { OrderConfirmationSweeper } from '../services/order-confirmation-sweeper.service.js';
import { OrderExpiryService } from '../services/order-expiry.service.js';
import {
  EXPIRE_STALE_ORDERS_JOB,
  ORDER_MAINTENANCE_QUEUE,
  SWEEP_CONFIRMATION_EMAILS_JOB,
} from './order-maintenance.constants.js';

@Processor(ORDER_MAINTENANCE_QUEUE)
export class OrderMaintenanceProcessor extends WorkerHost {
  private readonly logger = new Logger(OrderMaintenanceProcessor.name);
  private readonly connectionWarning = new ThrottledWarning(this.logger);

  constructor(
    private readonly orderExpiryService: OrderExpiryService,
    private readonly orderConfirmationSweeper: OrderConfirmationSweeper,
  ) {
    super();
  }

  process(job: Job): Promise<number> {
    switch (job.name) {
      case EXPIRE_STALE_ORDERS_JOB:
        return this.orderExpiryService.expireStaleOrders();
      case SWEEP_CONFIRMATION_EMAILS_JOB:
        return this.orderConfirmationSweeper.sweep();
      default:
        throw new Error(`Unknown order maintenance job "${job.name}"`);
    }
  }

  @OnWorkerEvent('failed')
  onFailed(job: Job | undefined, error: Error): void {
    this.logger.warn(`Maintenance job ${job?.name} failed: ${error.message}`);
  }

  @OnWorkerEvent('error')
  onError(error: Error): void {
    this.connectionWarning.warn(
      `Order maintenance worker unavailable (${describeError(error)})`,
    );
  }
}
