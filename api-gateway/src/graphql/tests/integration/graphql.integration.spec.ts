import { TestApp } from '../../../common/tests/integration/test-app.js';

interface GraphqlBody<T = Record<string, unknown>> {
  data: T | null;
  errors?: { message: string; extensions: { code: string } }[];
}

describe('GraphQL (integration)', () => {
  let testApp: TestApp;
  let adminToken: string;
  let customer: { id: number; token: string };
  let productId: number;

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
    customer = await testApp.signUp('ada@example.test');
    ({
      body: { id: productId },
    } = await testApp.request<{ id: number }>('POST', '/products', {
      body: { name: 'Blue Mug', sku: 'MUG-1', price: 12.5, initialStock: 5 },
      token: adminToken,
    }));
  });

  const graphql = <T>(query: string, token?: string) =>
    testApp.request<GraphqlBody<T>>('POST', '/graphql', {
      body: { query },
      token,
    });

  it('lists products like REST, also from the Redis cache', async () => {
    const query = `{ products(search: "mug", sortBy: Price, sortOrder: Desc) {
      data { id name price stock category { name } createdAt }
      meta { page limit total totalPages } } }`;

    const fresh = await graphql<{ products: unknown }>(query);
    const cached = await graphql<{ products: unknown }>(query);

    expect(fresh.body.data?.products).toEqual({
      data: [
        {
          id: productId,
          name: 'Blue Mug',
          price: '12.50',
          stock: 5,
          category: null,
          createdAt: expect.stringMatching(/^\d{4}-\d{2}-\d{2}T/),
        },
      ],
      meta: { page: 1, limit: 20, total: 1, totalPages: 1 },
    });
    expect(cached.body).toEqual(fresh.body);
  });

  it('treats filters sent as explicit nulls (as GraphQL clients do) like left-out ones', async () => {
    const { body } = await testApp.request<
      GraphqlBody<{
        products: { meta: { total: number } };
        myOrders: { meta: { total: number } };
      }>
    >('POST', '/graphql', {
      body: {
        query: `query ($category: Int, $min: Float, $max: Float, $status: OrderStatus) {
          products(categoryId: $category, minPrice: $min, maxPrice: $max) { meta { total } }
          myOrders(status: $status) { meta { total } } }`,
        variables: { category: null, min: null, max: null, status: null },
      },
      token: customer.token,
    });

    expect(body.errors).toBeUndefined();
    expect(body.data?.products.meta.total).toBe(1);
  });

  it('answers with the same rules and errors as REST', async () => {
    const notFound = await graphql('{ product(id: 999999) { name } }');
    const invalid = await graphql(
      '{ products(limit: 500) { meta { total } } }',
    );
    const anonymous = await graphql('{ me { email } }');

    expect(
      [notFound, invalid, anonymous].map(
        (response) => response.body.errors?.[0].extensions.code,
      ),
    ).toEqual(['NOT_FOUND', 'BAD_REQUEST', 'UNAUTHENTICATED']);
    expect(invalid.body.errors?.[0].message).toBe(
      'limit must not be greater than 100',
    );
  });

  it("shows customers their own orders and hides everyone else's", async () => {
    const { body: placed } = await testApp.request<{ id: number }>(
      'POST',
      '/orders',
      {
        body: {
          items: [{ productId, quantity: 2 }],
          shippingAddress: {
            fullName: 'Ada Lovelace',
            line1: '1 Main St',
            city: 'Sofia',
            postalCode: '1000',
            country: 'BG',
          },
          paymentToken: 'tok_visa',
        },
        token: customer.token,
      },
    );
    const { token: otherToken } = await testApp.signUp('bob@example.test');

    const own = await graphql<{
      me: { email: string };
      myOrders: { meta: { total: number } };
      order: { status: string; total: string; items: unknown[] };
    }>(
      `{ me { email } myOrders { meta { total } }
         order(id: ${placed.id}) { status total items { productName quantity lineTotal }
           shippingAddress { city country } } }`,
      customer.token,
    );
    const foreign = await graphql(
      `{ order(id: ${placed.id}) { status } }`,
      otherToken,
    );

    expect(own.body.data).toEqual({
      me: { email: 'ada@example.test' },
      myOrders: { meta: { total: 1 } },
      order: {
        status: 'Paid',
        total: '25.00',
        items: [{ productName: 'Blue Mug', quantity: 2, lineTotal: '25.00' }],
        shippingAddress: { city: 'Sofia', country: 'BG' },
      },
    });
    expect(foreign.body.errors?.[0].extensions.code).toBe('NOT_FOUND');
  });
});
