import type { ProductChangeNotifier } from '../../../products/product-change.notifier.js';
import type { CatalogWebhookDto } from '../../dto/catalog-webhook.dto.js';
import { CatalogEventType } from '../../enums/catalog-event-type.enum.js';
import { CatalogSyncService } from '../../services/catalog-sync.service.js';
import type { WebhookEventStore } from '../../webhook-event.store.js';

describe('CatalogSyncService', () => {
  let notifier: { productsChanged: ReturnType<typeof vi.fn> };
  let webhookEvents: { markIfNew: ReturnType<typeof vi.fn> };
  let service: CatalogSyncService;

  const event: CatalogWebhookDto = {
    eventId: '6f9619ff-8b86-4d11-b42d-00c04fc964ff',
    type: CatalogEventType.CatalogChanged,
    occurredAt: '2026-10-23T12:00:00.000Z',
  };

  beforeEach(() => {
    notifier = { productsChanged: vi.fn().mockResolvedValue(undefined) };
    webhookEvents = { markIfNew: vi.fn().mockResolvedValue(true) };
    service = new CatalogSyncService(
      notifier as unknown as ProductChangeNotifier,
      webhookEvents as unknown as WebhookEventStore,
    );
  });

  it('refreshes the product cache and search index for a new event', async () => {
    await expect(service.handle(event)).resolves.toBe('processed');

    expect(webhookEvents.markIfNew).toHaveBeenCalledWith(event.eventId);
    expect(notifier.productsChanged).toHaveBeenCalledTimes(1);
  });

  it('does nothing for a redelivered event', async () => {
    webhookEvents.markIfNew.mockResolvedValue(false);

    await expect(service.handle(event)).resolves.toBe('duplicate');

    expect(notifier.productsChanged).not.toHaveBeenCalled();
  });
});
