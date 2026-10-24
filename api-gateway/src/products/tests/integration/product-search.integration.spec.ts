import { Client } from '@elastic/elasticsearch';
import {
  ElasticsearchContainer,
  type StartedElasticsearchContainer,
} from '@testcontainers/elasticsearch';
import type * as TestAppModule from '../../../common/tests/integration/test-app.js';
import { PRODUCT_SEARCH_INDEX } from '../../search/product-search.constants.js';

/** Only this suite starts Elasticsearch; the others run with search off (Postgres). */
describe('Product search (integration)', () => {
  let elasticsearch: StartedElasticsearchContainer;
  let es: Client;
  let testApp: TestAppModule.TestApp;
  let waitFor: typeof TestAppModule.waitFor;
  let adminToken: string;

  beforeAll(async () => {
    elasticsearch = await new ElasticsearchContainer(
      'docker.elastic.co/elasticsearch/elasticsearch:9.5.3',
    )
      .withEnvironment({
        'xpack.security.enabled': 'false',
        ES_JAVA_OPTS: '-Xms512m -Xmx512m',
      })
      .start();
    process.env.ELASTICSEARCH_URL = elasticsearch.getHttpUrl();
    es = new Client({ node: elasticsearch.getHttpUrl() });

    // The app reads its configuration when it is imported, so only now.
    const testAppModule =
      await import('../../../common/tests/integration/test-app.js');
    waitFor = testAppModule.waitFor;
    testApp = await testAppModule.TestApp.create();
  }, 180_000);

  afterAll(async () => {
    await testApp?.close();
    await es?.close();
    // The last test already stopped it.
    await elasticsearch?.stop().catch(() => undefined);
  });

  beforeEach(async () => {
    await testApp.reset();
    await es.indices.delete({
      index: PRODUCT_SEARCH_INDEX,
      ignore_unavailable: true,
    });
    ({ token: adminToken } = await testApp.signUp(
      'admin@example.test',
      'admin',
    ));
  });

  const createProduct = async (
    name: string,
    sku: string,
    extra: { price?: number; description?: string } = {},
  ) =>
    (
      await testApp.request<{ id: number }>('POST', '/products', {
        body: { name, sku, price: 10, ...extra },
        token: adminToken,
      })
    ).body.id;

  const indexedCount = async () =>
    (
      await es
        .count({ index: PRODUCT_SEARCH_INDEX })
        .catch(() => ({ count: -1 }))
    ).count;

  const searchNames = async (query: string) => {
    const { body } = await testApp.request<{
      data: { name: string }[];
      meta: { total: number };
    }>('GET', `/products?${query}`);

    return body.data.map((product) => product.name);
  };

  /** A small catalogue, fully indexed. */
  const createCatalogue = async () => {
    await createProduct('Wireless Headphones', 'AUD-HP-01', { price: 129.9 });
    await createProduct('Desk Lamp', 'DEC-LMP-01', { price: 49.9 });
    await createProduct('Ceramic Mug', 'KIT-MUG-01', { price: 12.5 });
    await createProduct('Reading Light', 'DEC-RDL-01', {
      price: 29.9,
      description: 'A small lamp for reading in bed.',
    });
    await waitFor(async () => (await indexedCount()) === 4, 15_000);
  };

  const indexed = async (id: number) => {
    const exists = await es
      .exists({ index: PRODUCT_SEARCH_INDEX, id: String(id) })
      .catch(() => false);
    return exists
      ? (
          await es.get<{ name: string }>({
            index: PRODUCT_SEARCH_INDEX,
            id: String(id),
          })
        )._source
      : null;
  };

  /** What the admin service's catalogue webhook sends after it changed products. */
  const adminServiceChangedProducts = () =>
    testApp.request('POST', '/internal/webhooks/catalog', {
      body: {
        eventId: crypto.randomUUID(),
        type: 'catalog.changed',
        occurredAt: new Date().toISOString(),
      },
      headers: { 'x-api-key': process.env.INTERNAL_API_KEY ?? '' },
    });

  it('indexes products written through this API, and drops them once inactive', async () => {
    const mug = await createProduct('Blue Mug', 'MUG-1');
    await waitFor(async () => (await indexed(mug)) !== null, 15_000);

    await testApp.request('PUT', `/products/${mug}`, {
      body: { isActive: false },
      token: adminToken,
    });

    await waitFor(async () => (await indexed(mug)) === null, 15_000);
  });

  it('catches up with changes the admin service made straight in the database', async () => {
    const mug = await createProduct('Blue Mug', 'MUG-1');
    const lamp = await createProduct('Desk Lamp', 'LAMP-1');
    await waitFor(async () => (await indexed(lamp)) !== null, 15_000);

    await testApp.query(
      "UPDATE products SET name = 'Red Mug', updated_at = NOW() WHERE id = $1",
      [mug],
    );
    await testApp.query(
      'UPDATE products SET deleted_at = NOW(), updated_at = NOW() WHERE id = $1',
      [lamp],
    );
    await adminServiceChangedProducts();

    await waitFor(
      async () =>
        (await indexed(mug))?.name === 'Red Mug' &&
        (await indexed(lamp)) === null,
      15_000,
    );
  });

  it('rebuilds a lost index completely and deletes outdated versions', async () => {
    const mug = await createProduct('Blue Mug', 'MUG-1');
    await waitFor(async () => (await indexed(mug)) !== null, 15_000);

    await es.indices.delete({ index: PRODUCT_SEARCH_INDEX });
    await es.indices.create({ index: 'products_v1' });
    await adminServiceChangedProducts();

    await waitFor(async () => (await indexed(mug)) !== null, 15_000);
    expect(await es.indices.exists({ index: 'products_v1' })).toBe(false);
  });

  it('forgives typos, matches word starts and exact SKUs', async () => {
    await createCatalogue();

    expect(await searchNames('search=headphnes')).toEqual([
      'Wireless Headphones',
    ]);
    // As you type: the last word may be unfinished.
    expect(await searchNames('search=wireless%20headph')).toEqual([
      'Wireless Headphones',
    ]);
    expect(await searchNames('search=headphones%20wireless')).toEqual([
      'Wireless Headphones',
    ]);
    expect(await searchNames('search=kit-mug-01')).toEqual(['Ceramic Mug']);
  });

  it('puts the best match first when sorting by relevance, and filters like REST', async () => {
    await createCatalogue();

    expect(await searchNames('search=lamp&sortBy=relevance')).toEqual([
      'Desk Lamp',
      'Reading Light',
    ]);
    expect(await searchNames('search=lamp&maxPrice=40')).toEqual([
      'Reading Light',
    ]);
    expect(await searchNames('search=lamp&sortBy=price&sortOrder=asc')).toEqual(
      ['Reading Light', 'Desk Lamp'],
    );
  });

  it('searches the same way through GraphQL', async () => {
    await createCatalogue();

    const { body } = await testApp.request<{
      data: { products: { data: { name: string }[] } };
    }>('POST', '/graphql', {
      body: {
        query:
          '{ products(search: "headphnes", sortBy: Relevance) { data { name } } }',
      },
    });

    expect(body.data.products.data).toEqual([{ name: 'Wireless Headphones' }]);
  });

  // Last: it stops Elasticsearch.
  it('falls back to Postgres when Elasticsearch is down, and reports it', async () => {
    await createCatalogue();
    const healthy = await testApp.request<{ checks: object }>('GET', '/health');

    await elasticsearch.stop();

    const degraded = await testApp.request<{
      status: string;
      checks: object;
    }>('GET', '/health');
    expect(healthy.body.checks).toMatchObject({ elasticsearch: 'up' });
    expect(degraded.body).toMatchObject({
      status: 'degraded',
      checks: { elasticsearch: 'down' },
    });
    // Plain substring search still works; typo tolerance needs Elasticsearch.
    expect(await searchNames('search=lamp')).toEqual(['Desk Lamp']);
    expect(await searchNames('search=headphnes')).toEqual([]);
  });
});
