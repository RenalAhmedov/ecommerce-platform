import { Injectable, Logger } from '@nestjs/common';
import { ProductsCache } from '../../products/products.cache.js';
import type { CatalogWebhookDto } from '../dto/catalog-webhook.dto.js';
import { WebhookEventStore } from '../webhook-event.store.js';

export type CatalogSyncResult = 'processed' | 'duplicate';

/**
 * Keeps this service in step with catalogue changes made in the admin
 * service. Both share one database, so there is no data to copy: the cached
 * product responses are invalidated so the next read sees the change at once.
 */
@Injectable()
export class CatalogSyncService {
  private readonly logger = new Logger(CatalogSyncService.name);

  constructor(
    private readonly productsCache: ProductsCache,
    private readonly webhookEvents: WebhookEventStore,
  ) {}

  async handle(event: CatalogWebhookDto): Promise<CatalogSyncResult> {
    if (!(await this.webhookEvents.markIfNew(event.eventId))) {
      this.logger.log(
        `Ignored redelivered ${event.type} event ${event.eventId}`,
      );
      return 'duplicate';
    }

    await this.productsCache.invalidate();
    this.logger.log(
      `Product cache invalidated by ${event.type} event ${event.eventId} (occurred ${event.occurredAt})`,
    );

    return 'processed';
  }
}
