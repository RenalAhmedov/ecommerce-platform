import { TestApp } from '../../../common/tests/integration/test-app.js';

interface ProductBody {
  id: number;
  price: string;
  stock: number;
}

describe('Products (integration)', () => {
  let testApp: TestApp;
  let adminToken: string;

  beforeAll(async () => {
    testApp = await TestApp.create();
  });

  afterAll(() => testApp.close());

  beforeEach(async () => {
    await testApp.reset();
    ({ token: adminToken } = await testApp.signUp(
      'admin@example.test',
      'admin',
    ));
  });

  const createProduct = (body: Record<string, unknown>) =>
    testApp.request<ProductBody>('POST', '/products', {
      body,
      token: adminToken,
    });

  it('creates a product with its stock row and initial ledger entry', async () => {
    const created = await createProduct({
      name: 'Mechanical Keyboard',
      sku: 'mk-001',
      price: 89.9,
      initialStock: 25,
    });

    expect(created.status).toBe(201);
    expect(created.body).toMatchObject({
      sku: 'MK-001',
      slug: 'mechanical-keyboard',
      price: '89.90',
      stock: 25,
    });
    expect(
      await testApp.query(
        'SELECT change, reason FROM inventory_movements WHERE product_id = $1',
        [created.body.id],
      ),
    ).toEqual([{ change: 25, reason: 'initial' }]);
  });

  it('serves reads from Redis and invalidates them after an update', async () => {
    const { body: product } = await createProduct({
      name: 'Mug',
      sku: 'MUG-1',
      price: 5,
    });

    await testApp.request('GET', `/products/${product.id}`);
    const cachedKeys = await testApp.redis.keys(
      `products:v*:detail:${product.id}`,
    );

    await testApp.request('PUT', `/products/${product.id}`, {
      body: { price: 7.5 },
      token: adminToken,
    });
    const afterUpdate = await testApp.request<ProductBody>(
      'GET',
      `/products/${product.id}`,
    );

    expect(cachedKeys).toHaveLength(1);
    expect(afterUpdate.body.price).toBe('7.50');
  });

  it('filters and paginates the public list', async () => {
    for (const [name, price] of [
      ['Red Mug', 5],
      ['Blue Mug', 15],
      ['Keyboard', 90],
    ] as const) {
      await createProduct({ name, sku: name.replace(' ', '-'), price });
    }

    const mugs = await testApp.request<{
      data: { name: string }[];
      meta: { total: number };
    }>('GET', '/products?search=mug&maxPrice=10');
    const firstPage = await testApp.request<{
      data: unknown[];
      meta: { totalPages: number };
    }>('GET', '/products?limit=2&sortBy=price&sortOrder=asc');

    expect(mugs.body.data.map((product) => product.name)).toEqual(['Red Mug']);
    expect(firstPage.body.data).toHaveLength(2);
    expect(firstPage.body.meta.totalPages).toBe(2);
  });

  it('only lets admins write', async () => {
    const { token: customerToken } = await testApp.signUp(
      'customer@example.test',
    );
    const body = { name: 'Mug', sku: 'MUG-1', price: 5 };

    const anonymous = await testApp.request('POST', '/products', { body });
    const customer = await testApp.request('POST', '/products', {
      body,
      token: customerToken,
    });

    expect([anonymous.status, customer.status]).toEqual([401, 403]);
  });

  it('creates exactly one product for 10 concurrent creates of the same SKU', async () => {
    const responses = await Promise.all(
      Array.from({ length: 10 }, (_, index) =>
        createProduct({ name: `Mug ${index}`, sku: 'MUG-1', price: 5 }),
      ),
    );

    expect(
      responses.filter((response) => response.status === 201),
    ).toHaveLength(1);
    expect(
      await testApp.query("SELECT id FROM products WHERE sku = 'MUG-1'"),
    ).toHaveLength(1);
  });
});
