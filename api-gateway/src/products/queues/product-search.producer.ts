import { InjectQueue } from '@nestjs/bullmq';
import {
  Injectable,
  Logger,
  type OnApplicationBootstrap,
} from '@nestjs/common';
import type { JobsOptions, Queue } from 'bullmq';
import { ThrottledWarning } from '../../common/logging/throttled-warning.js';
import { describeError } from '../../common/utils/describe-error.js';
import {
  PRODUCT_SEARCH_QUEUE,
  PRODUCT_SEARCH_SYNC_INTERVAL_MS,
  SYNC_PRODUCT_SEARCH_JOB,
} from '../search/product-search.constants.js';
import { ProductSearchRepository } from '../search/product-search.repository.js';

const SYNC_JOB_OPTIONS: JobsOptions = {
  // At most one sync running and one waiting: a change made during a sync
  // always gets a sync after it, and two never run at the same time.
  deduplication: { id: SYNC_PRODUCT_SEARCH_JOB, keepLastIfActive: true },
  attempts: 5,
  backoff: { type: 'exponential', delay: 5_000 },
  removeOnComplete: { count: 100 },
  removeOnFail: { count: 100 },
};

@Injectable()
export class ProductSearchProducer implements OnApplicationBootstrap {
  private readonly logger = new Logger(ProductSearchProducer.name);
  private readonly unavailableWarning = new ThrottledWarning(this.logger);

  constructor(
    @InjectQueue(PRODUCT_SEARCH_QUEUE) private readonly queue: Queue,
    private readonly productSearchRepository: ProductSearchRepository,
  ) {
    this.queue.on('error', (error) =>
      this.unavailableWarning.warn(
        `Product search queue unavailable (${describeError(error)})`,
      ),
    );
  }

  /** Never fails the caller: a lost request is caught up by the scheduled sync. */
  async requestSync(): Promise<void> {
    if (!this.productSearchRepository.isEnabled()) {
      return;
    }

    try {
      await this.queue.add(SYNC_PRODUCT_SEARCH_JOB, {}, SYNC_JOB_OPTIONS);
    } catch (error) {
      this.unavailableWarning.warn(
        `Could not request a search index sync: ${describeError(error)}`,
      );
    }
  }

  onApplicationBootstrap(): void {
    if (this.productSearchRepository.isEnabled()) {
      void this.scheduleSyncs();
    }
  }

  /** A sync at start (builds the index the first time) and then every few minutes. */
  private async scheduleSyncs(): Promise<void> {
    try {
      await this.queue.waitUntilReady();
      await this.queue.upsertJobScheduler(
        SYNC_PRODUCT_SEARCH_JOB,
        { every: PRODUCT_SEARCH_SYNC_INTERVAL_MS },
        { name: SYNC_PRODUCT_SEARCH_JOB, opts: SYNC_JOB_OPTIONS },
      );
      await this.requestSync();
    } catch (error) {
      this.logger.error(
        `Could not schedule search index syncs: ${describeError(error)}`,
      );
    }
  }
}
