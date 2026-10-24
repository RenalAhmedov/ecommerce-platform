import { Logger } from '@nestjs/common';
import { ProductQueryDto } from '../../dto/product-query.dto.js';
import type { ProductsRepository } from '../../products.repository.js';
import type { ProductSearchRepository } from '../../search/product-search.repository.js';
import { ProductSearchService } from '../../search/product-search.service.js';

describe('ProductSearchService', () => {
  let products: {
    findActiveByIds: ReturnType<typeof vi.fn>;
    findActivePaginated: ReturnType<typeof vi.fn>;
  };
  let index: {
    isEnabled: ReturnType<typeof vi.fn>;
    search: ReturnType<typeof vi.fn>;
  };
  let service: ProductSearchService;
  const filters = Object.assign(new ProductQueryDto(), { search: 'mug' });

  beforeEach(() => {
    products = {
      findActiveByIds: vi.fn().mockResolvedValue([{ id: 2 }, { id: 1 }]),
      findActivePaginated: vi.fn().mockResolvedValue([[{ id: 1 }], 1]),
    };
    index = {
      isEnabled: vi.fn().mockReturnValue(true),
      search: vi.fn().mockResolvedValue({ ids: [2, 1], total: 7 }),
    };
    service = new ProductSearchService(
      products as unknown as ProductsRepository,
      index as unknown as ProductSearchRepository,
    );
    vi.spyOn(Logger.prototype, 'warn').mockImplementation(() => undefined);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('lets Elasticsearch find and order the matches, and reads them from Postgres', async () => {
    await expect(service.search(filters)).resolves.toEqual([
      [{ id: 2 }, { id: 1 }],
      7,
    ]);
    expect(products.findActiveByIds).toHaveBeenCalledWith([2, 1]);
    expect(products.findActivePaginated).not.toHaveBeenCalled();
  });

  it('searches Postgres when Elasticsearch is down', async () => {
    index.search.mockRejectedValue(new Error('ECONNREFUSED'));

    await expect(service.search(filters)).resolves.toEqual([[{ id: 1 }], 1]);
    expect(products.findActivePaginated).toHaveBeenCalledWith(filters);
  });

  it('searches Postgres when Elasticsearch is not configured', async () => {
    index.isEnabled.mockReturnValue(false);

    await service.search(filters);

    expect(index.search).not.toHaveBeenCalled();
    expect(products.findActivePaginated).toHaveBeenCalledWith(filters);
  });
});
