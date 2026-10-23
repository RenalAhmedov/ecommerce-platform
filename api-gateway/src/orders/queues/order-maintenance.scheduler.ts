import { InjectQueue } from '@nestjs/bullmq';
import {
  Injectable,
  Logger,
  type OnApplicationBootstrap,
} from '@nestjs/common';
import type { Queue } from 'bullmq';
import { ThrottledWarning } from '../../common/logging/throttled-warning.js';
import { describeError } from '../../common/utils/describe-error.js';
import {
  EXPIRE_STALE_ORDERS_JOB,
  MAINTENANCE_INTERVAL_MS,
  MAINTENANCE_JOB_OPTIONS,
  ORDER_MAINTENANCE_QUEUE,
  SWEEP_CONFIRMATION_EMAILS_JOB,
} from './order-maintenance.constants.js';

@Injectable()
export class OrderMaintenanceScheduler implements OnApplicationBootstrap {
  private readonly logger = new Logger(OrderMaintenanceScheduler.name);

  constructor(
    @InjectQueue(ORDER_MAINTENANCE_QUEUE) private readonly queue: Queue,
  ) {
    const connectionWarning = new ThrottledWarning(this.logger);

    this.queue.on('error', (error) =>
      connectionWarning.warn(
        `Order maintenance queue unavailable (${describeError(error)})`,
      ),
    );
  }

  onApplicationBootstrap(): void {
    void this.registerSchedules();
  }

  private async registerSchedules(): Promise<void> {
    try {
      await this.queue.waitUntilReady();

      for (const jobName of [
        EXPIRE_STALE_ORDERS_JOB,
        SWEEP_CONFIRMATION_EMAILS_JOB,
      ]) {
        await this.queue.upsertJobScheduler(
          jobName,
          { every: MAINTENANCE_INTERVAL_MS },
          { name: jobName, opts: MAINTENANCE_JOB_OPTIONS },
        );
      }
    } catch (error) {
      this.logger.error(
        `Could not register order maintenance schedules: ${describeError(error)}`,
      );
    }
  }
}
