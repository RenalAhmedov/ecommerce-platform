import { Module } from '@nestjs/common';
import { ProductsModule } from '../products/products.module.js';
import { ApiKeyGuard } from './guards/api-key.guard.js';
import { CatalogSyncService } from './services/catalog-sync.service.js';
import { WebhookEventStore } from './webhook-event.store.js';
import { WebhooksController } from './webhooks.controller.js';

@Module({
  imports: [ProductsModule],
  controllers: [WebhooksController],
  providers: [ApiKeyGuard, CatalogSyncService, WebhookEventStore],
})
export class WebhooksModule {}
