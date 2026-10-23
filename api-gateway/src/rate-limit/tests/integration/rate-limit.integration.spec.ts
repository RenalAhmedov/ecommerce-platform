import { TestApp } from '../../../common/tests/integration/test-app.js';

vi.hoisted(() => {
  process.env.RATE_LIMIT_ENABLED = 'true';
});

describe('Rate limiting (integration)', () => {
  let testApp: TestApp;

  beforeAll(async () => {
    testApp = await TestApp.create();
  });

  afterAll(() => testApp.close());

  beforeEach(() => testApp.reset());

  it('allows 5 logins per minute per IP, then answers 429 with Retry-After', async () => {
    await testApp.signUp('ada@example.test');

    const responses = [];
    for (let attempt = 0; attempt < 5; attempt++) {
      responses.push(
        await testApp.request('POST', '/auth/login', {
          body: { email: 'ada@example.test', password: 'password123' },
        }),
      );
    }

    const blocked = responses[4];
    expect(responses.map((response) => response.status)).toEqual([
      200, 200, 200, 200, 429,
    ]);
    expect(Number(blocked.headers.get('retry-after'))).toBeGreaterThan(0);
    expect(blocked.body).toMatchObject({
      statusCode: 429,
      error: 'Too Many Requests',
      message: 'Too many requests, please try again later',
    });
    expect(responses[0].headers.get('x-ratelimit-limit')).toBe('5');
  });

  it('limits orders per user, so customers behind the same IP keep their own quota', async () => {
    const { token: adminToken } = await testApp.signUp(
      'admin@example.test',
      'admin',
    );
    const alice = await testApp.signUp('alice@example.test');
    const bob = await testApp.signUp('bob@example.test');
    const { body: product } = await testApp.request<{ id: number }>(
      'POST',
      '/products',
      {
        body: { name: 'Mug', sku: 'MUG-1', price: 5, initialStock: 100 },
        token: adminToken,
      },
    );
    const order = (token: string) =>
      testApp.request('POST', '/orders', {
        body: {
          items: [{ productId: product.id, quantity: 1 }],
          shippingAddress: {
            fullName: 'Ada',
            line1: '1 Main St',
            city: 'Sofia',
            postalCode: '1000',
            country: 'BG',
          },
          paymentToken: 'tok_visa',
        },
        token,
      });

    const aliceStatuses = [];
    for (let attempt = 0; attempt < 11; attempt++) {
      aliceStatuses.push((await order(alice.token)).status);
    }
    const bobStatus = (await order(bob.token)).status;

    expect(aliceStatuses.filter((status) => status === 201)).toHaveLength(10);
    expect(aliceStatuses[10]).toBe(429);
    expect(bobStatus).toBe(201);
  });
});
