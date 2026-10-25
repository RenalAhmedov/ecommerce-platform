import { randomUUID } from 'node:crypto';
import { TestApp } from '../../../common/tests/integration/test-app.js';

describe('Catalog webhook (integration)', () => {
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

  const event = () => ({
    eventId: randomUUID(),
    type: 'catalog.changed',
    occurredAt: new Date().toISOString(),
  });

  // null sends no X-Api-Key header at all.
  const sendWebhook = (
    body: object,
    apiKey: string | null = process.env.INTERNAL_API_KEY ?? null,
  ) =>
    testApp.request<{ status: string }>('POST', '/internal/webhooks/catalog', {
      body,
      headers: apiKey === null ? {} : { 'x-api-key': apiKey },
    });

  it('refuses callers without the shared API key, even with an admin token', async () => {
    const noKey = await sendWebhook(event(), null);
    const wrongKey = await sendWebhook(event(), 'not-the-right-key');
    const tokenOnly = await testApp.request(
      'POST',
      '/internal/webhooks/catalog',
      { body: event(), token: adminToken },
    );

    expect([noKey.status, wrongKey.status, tokenOnly.status]).toEqual([
      401, 401, 401,
    ]);
  });

  it('validates the event', async () => {
    const response = await sendWebhook({ eventId: 'nope', type: 'other' });

    expect(response.status).toBe(400);
  });

  it('makes a change written by the admin service visible at once', async () => {
    const { body: product } = await testApp.request<{ id: number }>(
      'POST',
      '/products',
      {
        body: { name: 'Mug', sku: 'MUG-1', price: 5 },
        token: adminToken,
      },
    );
    await testApp.request('GET', `/products/${product.id}`);

    // The admin service writes straight to the shared database, bypassing this API's cache.
    await testApp.query('UPDATE products SET price = 7.5 WHERE id = $1', [
      product.id,
    ]);
    const beforeWebhook = await testApp.request<{ price: string }>(
      'GET',
      `/products/${product.id}`,
    );

    const webhook = await sendWebhook(event());
    const afterWebhook = await testApp.request<{ price: string }>(
      'GET',
      `/products/${product.id}`,
    );

    expect(beforeWebhook.body.price).toBe('5.00');
    expect(webhook).toMatchObject({
      status: 202,
      body: { status: 'processed' },
    });
    expect(afterWebhook.body.price).toBe('7.50');
  });

  it('handles a redelivered event once', async () => {
    const delivery = event();

    const first = await sendWebhook(delivery);
    const second = await sendWebhook(delivery);

    expect(first.body.status).toBe('processed');
    expect(second).toMatchObject({
      status: 202,
      body: { status: 'duplicate' },
    });
  });
});
