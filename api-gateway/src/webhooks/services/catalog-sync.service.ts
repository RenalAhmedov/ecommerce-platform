import { Injectable, Logger } from '@nestjs/common';
import { ProductChangeNotifier } from '../../products/product-change.notifier.js';
import type { CatalogWebhookDto } from '../dto/catalog-webhook.dto.js';
import { WebhookEventStore } from '../webhook-event.store.js';

export type CatalogSyncResult = 'processed' | 'duplicate';

@Injectable()
export class CatalogSyncService {
  private readonly logger = new Logger(CatalogSyncService.name);

  constructor(
    private readonly productChangeNotifier: ProductChangeNotifier,
    private readonly webhookEvents: WebhookEventStore,
  ) {}

  async handle(event: CatalogWebhookDto): Promise<CatalogSyncResult> {
    if (!(await this.webhookEvents.markIfNew(event.eventId))) {
      this.logger.log(
        `Ignored redelivered ${event.type} event ${event.eventId}`,
      );
      return 'duplicate';
    }

    await this.productChangeNotifier.productsChanged();
    this.logger.log(
      `Product cache invalidated and search sync requested by ${event.type} event ${event.eventId} (occurred ${event.occurredAt})`,
    );

    return 'processed';
  }
}
