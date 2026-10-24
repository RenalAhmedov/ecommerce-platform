import { Injectable, Logger } from '@nestjs/common';
import { ProductsCache } from '../products.cache.js';
import { ProductsRepository } from '../products.repository.js';
import {
  PRODUCT_SEARCH_BATCH_SIZE,
  PRODUCT_SEARCH_SYNC_OVERLAP_MS,
} from './product-search.constants.js';
import { isSearchable, toSearchDocument } from './product-search-document.js';
import { ProductSearchRepository } from './product-search.repository.js';

export interface ProductSearchSyncResult {
  indexed: number;
  removed: number;
}

/**
 * Brings the search index up to date with Postgres, whichever service changed
 * the products: everything created, changed or deleted since the last sync is
 * written again (active products) or removed (inactive or deleted ones).
 * Writing a product again is harmless, so a sync can be repeated or overlap
 * the previous one; it runs on a queue with one worker, never twice at once.
 */
@Injectable()
export class ProductSearchSyncService {
  private readonly logger = new Logger(ProductSearchSyncService.name);

  constructor(
    private readonly productsRepository: ProductsRepository,
    private readonly productSearchRepository: ProductSearchRepository,
    private readonly productsCache: ProductsCache,
  ) {}

  async sync(): Promise<ProductSearchSyncResult> {
    if (!this.productSearchRepository.isEnabled()) {
      return { indexed: 0, removed: 0 };
    }

    const startedAt = new Date();
    const created = await this.productSearchRepository.ensureIndex();
    const syncedUntil = created
      ? null
      : await this.productSearchRepository.syncedUntil();
    const since = syncedUntil
      ? new Date(syncedUntil.getTime() - PRODUCT_SEARCH_SYNC_OVERLAP_MS)
      : null;

    const changedIds = await this.productsRepository.findIdsChangedSince(since);
    const result = { indexed: 0, removed: 0 };

    for (let i = 0; i < changedIds.length; i += PRODUCT_SEARCH_BATCH_SIZE) {
      const batch = await this.syncBatch(
        changedIds.slice(i, i + PRODUCT_SEARCH_BATCH_SIZE),
      );
      result.indexed += batch.indexed;
      result.removed += batch.removed;
    }

    await this.productSearchRepository.markSyncedUntil(startedAt);

    if (created || changedIds.length > 0) {
      // Searches cached before the index changed (or while it was missing and
      // Postgres answered instead) would otherwise be served for another minute.
      await this.productsCache.invalidate();
      this.logger.log(
        `Search index ${created ? 'built' : 'updated'}: ${result.indexed} product(s) indexed, ${result.removed} removed in ${Date.now() - startedAt.getTime()}ms`,
      );
    }

    return result;
  }

  private async syncBatch(ids: number[]): Promise<ProductSearchSyncResult> {
    const products = await this.productsRepository.findIndexableByIds(ids);
    const searchable = products.filter(isSearchable);
    const searchableIds = new Set(searchable.map((product) => product.id));
    // Inactive, deleted, or gone from the table altogether.
    const removals = ids.filter((id) => !searchableIds.has(id));

    await this.productSearchRepository.apply(
      searchable.map(toSearchDocument),
      removals,
    );

    return { indexed: searchable.length, removed: removals.length };
  }
}
