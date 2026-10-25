import type { Client } from '@elastic/elasticsearch';
import { Inject, Injectable } from '@nestjs/common';
import { ELASTICSEARCH_CLIENT } from '../../elasticsearch/elasticsearch.constants.js';
import {
  PRODUCT_SEARCH_INDEX,
  PRODUCT_SEARCH_INDEX_PATTERN,
} from './product-search.constants.js';
import type { ProductSearchDocument } from './product-search-document.js';
import {
  PRODUCT_SEARCH_MAPPINGS,
  PRODUCT_SEARCH_SETTINGS,
} from './product-search.index-definition.js';
import {
  buildProductSearchRequest,
  type ProductSearchFilters,
} from './product-search.query.js';

interface ProductSearchHits {
  ids: number[];
  total: number;
}

@Injectable()
export class ProductSearchRepository {
  constructor(
    @Inject(ELASTICSEARCH_CLIENT) private readonly client: Client | null,
  ) {}

  isEnabled(): boolean {
    return this.client !== null;
  }

  async search(filters: ProductSearchFilters): Promise<ProductSearchHits> {
    const response = await this.es().search(buildProductSearchRequest(filters));
    const { total } = response.hits;

    return {
      ids: response.hits.hits.map((hit) => Number(hit._id)),
      total: typeof total === 'number' ? total : (total?.value ?? 0),
    };
  }

  async ensureIndex(): Promise<boolean> {
    if (await this.es().indices.exists({ index: PRODUCT_SEARCH_INDEX })) {
      return false;
    }

    try {
      await this.es().indices.create({
        index: PRODUCT_SEARCH_INDEX,
        settings: PRODUCT_SEARCH_SETTINGS,
        mappings: PRODUCT_SEARCH_MAPPINGS,
      });
    } catch (error) {
      // Another instance created it in the meantime.
      if (!isAlreadyExists(error)) {
        throw error;
      }
    }

    await this.deleteOutdatedIndices();

    return true;
  }

  async syncedUntil(): Promise<Date | null> {
    const response = await this.es().indices.getMapping({
      index: PRODUCT_SEARCH_INDEX,
    });
    const value = response[PRODUCT_SEARCH_INDEX]?.mappings._meta?.syncedUntil;

    return typeof value === 'string' ? new Date(value) : null;
  }

  async markSyncedUntil(time: Date): Promise<void> {
    await this.es().indices.putMapping({
      index: PRODUCT_SEARCH_INDEX,
      _meta: { syncedUntil: time.toISOString() },
    });
  }

  async apply(
    upserts: ProductSearchDocument[],
    removals: number[],
  ): Promise<void> {
    if (upserts.length === 0 && removals.length === 0) {
      return;
    }

    const response = await this.es().bulk({
      index: PRODUCT_SEARCH_INDEX,
      refresh: true,
      operations: [
        ...upserts.flatMap((document) => [
          { index: { _id: String(document.id) } },
          document,
        ]),
        ...removals.map((id) => ({ delete: { _id: String(id) } })),
      ],
    });

    // A delete of a product that was never indexed answers 404; that is fine.
    const failed = response.items.filter((item) => {
      const result = item.index ?? item.delete;
      return result?.error && result.status !== 404;
    });

    if (failed.length > 0) {
      throw new Error(
        `Search index rejected ${failed.length} of ${response.items.length} changes`,
      );
    }
  }

  // By name: Elasticsearch refuses wildcard deletes.
  private async deleteOutdatedIndices(): Promise<void> {
    const indices = await this.es().indices.get({
      index: PRODUCT_SEARCH_INDEX_PATTERN,
    });
    const outdated = Object.keys(indices).filter(
      (name) => name !== PRODUCT_SEARCH_INDEX,
    );

    if (outdated.length > 0) {
      await this.es().indices.delete({ index: outdated });
    }
  }

  private es(): Client {
    if (!this.client) {
      throw new Error('Elasticsearch is not configured (ELASTICSEARCH_URL)');
    }

    return this.client;
  }
}

function isAlreadyExists(error: unknown): boolean {
  return (
    error instanceof Error &&
    'body' in error &&
    JSON.stringify(error.body).includes('resource_already_exists_exception')
  );
}
