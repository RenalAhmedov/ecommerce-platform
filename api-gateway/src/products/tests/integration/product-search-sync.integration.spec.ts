import { Client } from '@elastic/elasticsearch';
import {
  ElasticsearchContainer,
  type StartedElasticsearchContainer,
} from '@testcontainers/elasticsearch';
import type * as TestAppModule from '../../../common/tests/integration/test-app.js';
import { PRODUCT_SEARCH_INDEX } from '../../search/product-search.constants.js';

/** Only this suite starts Elasticsearch; the others run with search off (Postgres). */
describe('Product search index sync (integration)', () => {
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
    await elasticsearch?.stop();
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

  const createProduct = async (name: string, sku: string) =>
    (
      await testApp.request<{ id: number }>('POST', '/products', {
        body: { name, sku, price: 10 },
        token: adminToken,
      })
    ).body.id;

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

  it('rebuilds a lost index completely', async () => {
    const mug = await createProduct('Blue Mug', 'MUG-1');
    await waitFor(async () => (await indexed(mug)) !== null, 15_000);

    await es.indices.delete({ index: PRODUCT_SEARCH_INDEX });
    await adminServiceChangedProducts();

    await waitFor(async () => (await indexed(mug)) !== null, 15_000);
  });
});
