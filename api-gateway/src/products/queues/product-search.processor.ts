import { OnWorkerEvent, Processor, WorkerHost } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import type { Job } from 'bullmq';
import { ThrottledWarning } from '../../common/logging/throttled-warning.js';
import { describeError } from '../../common/utils/describe-error.js';
import { PRODUCT_SEARCH_QUEUE } from '../search/product-search.constants.js';
import {
  ProductSearchSyncService,
  type ProductSearchSyncResult,
} from '../search/product-search-sync.service.js';

@Processor(PRODUCT_SEARCH_QUEUE)
export class ProductSearchProcessor extends WorkerHost {
  private readonly logger = new Logger(ProductSearchProcessor.name);
  private readonly connectionWarning = new ThrottledWarning(this.logger);

  constructor(
    private readonly productSearchSyncService: ProductSearchSyncService,
  ) {
    super();
  }

  process(): Promise<ProductSearchSyncResult> {
    return this.productSearchSyncService.sync();
  }

  @OnWorkerEvent('failed')
  onFailed(job: Job | undefined, error: Error): void {
    this.logger.warn(
      `Search index sync failed (attempt ${job?.attemptsMade}), it will be retried: ${error.message}`,
    );
  }

  @OnWorkerEvent('error')
  onError(error: Error): void {
    this.connectionWarning.warn(
      `Product search worker unavailable (${describeError(error)})`,
    );
  }
}
