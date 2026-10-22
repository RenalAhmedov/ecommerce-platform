import {
  TestApp,
  waitFor,
} from '../../../common/tests/integration/test-app.js';
import { OrderExpiryService } from '../../services/order-expiry.service.js';

interface OrderBody {
  id: number;
  orderNumber: string;
  status: string;
  total: string;
  items: { unitPrice: string; lineTotal: string }[];
}

describe('Orders (integration)', () => {
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
    customer = await testApp.signUp('customer@example.test');
    productId = await createProduct(12.5, 5);
  });

  async function createProduct(price: number, initialStock: number) {
    const { body } = await testApp.request<{ id: number }>(
      'POST',
      '/products',
      {
        body: {
          name: `Mug ${price}`,
          sku: `MUG-${price}`.replace('.', '-'),
          price,
          initialStock,
        },
        token: adminToken,
      },
    );
    return body.id;
  }

  const placeOrder = (
    quantity: number,
    {
      paymentToken = 'tok_visa',
      token = customer.token,
      idempotencyKey,
    }: { paymentToken?: string; token?: string; idempotencyKey?: string } = {},
  ) =>
    testApp.request<OrderBody>('POST', '/orders', {
      body: {
        items: [{ productId, quantity }],
        shippingAddress: {
          fullName: 'Ada Lovelace',
          line1: '1 Main St',
          city: 'Sofia',
          postalCode: '1000',
          country: 'BG',
        },
        paymentToken,
      },
      token,
      headers: idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {},
    });

  const stock = async () =>
    (
      await testApp.query<{ quantity: number }>(
        'SELECT quantity FROM inventories WHERE product_id = $1',
        [productId],
      )
    )[0].quantity;

  it('places a paid order, decrements stock and emails the customer', async () => {
    const response = await placeOrder(2);

    expect(response.status).toBe(201);
    expect(response.body).toMatchObject({ status: 'paid', total: '25.00' });
    expect(await stock()).toBe(3);
    expect(
      await testApp.query(
        'SELECT change, reason FROM inventory_movements WHERE order_id = $1',
        [response.body.id],
      ),
    ).toEqual([{ change: -2, reason: 'sale' }]);

    await waitFor(() => testApp.sendMail.mock.calls.length > 0);
    expect(testApp.sendMail).toHaveBeenCalledWith(
      expect.objectContaining({
        to: 'customer@example.test',
        subject: `Your order ${response.body.orderNumber} is confirmed`,
      }),
    );
  });

  it('keeps a declined order as payment_failed and gives the stock back', async () => {
    const response = await placeOrder(2, { paymentToken: 'tok_declined' });

    const [order] = await testApp.query<{ id: number; status: string }>(
      'SELECT id, status FROM orders',
    );
    expect(response.status).toBe(402);
    expect(order.status).toBe('payment_failed');
    expect(await stock()).toBe(5);
    expect(
      await testApp.query(
        'SELECT change, reason FROM inventory_movements WHERE order_id = $1 ORDER BY id',
        [order.id],
      ),
    ).toEqual([
      { change: -2, reason: 'sale' },
      { change: 2, reason: 'cancellation' },
    ]);
    expect(testApp.sendMail).not.toHaveBeenCalled();
  });

  it('never oversells: 10 buyers race for the last 5 units', async () => {
    const responses = await Promise.all(
      Array.from({ length: 10 }, () => placeOrder(1)),
    );

    const statuses = responses.map((response) => response.status);
    expect(statuses.filter((status) => status === 201)).toHaveLength(5);
    expect(statuses.filter((status) => status === 409)).toHaveLength(5);
    expect(await stock()).toBe(0);
    expect(
      await testApp.query("SELECT id FROM orders WHERE status = 'paid'"),
    ).toHaveLength(5);
  });

  it('returns the same order for a retried Idempotency-Key', async () => {
    const first = await placeOrder(1, { idempotencyKey: 'checkout-42' });
    const retry = await placeOrder(1, { idempotencyKey: 'checkout-42' });

    expect(retry.status).toBe(201);
    expect(retry.body.id).toBe(first.body.id);
    expect(retry.headers.get('idempotent-replayed')).toBe('true');
    expect(await testApp.query('SELECT id FROM orders')).toHaveLength(1);
    expect(await stock()).toBe(4);
  });

  it('keeps the price paid even after the product price changes', async () => {
    const { body: order } = await placeOrder(1);

    await testApp.request('PUT', `/products/${productId}`, {
      body: { price: 99 },
      token: adminToken,
    });
    const reloaded = await testApp.request<OrderBody>(
      'GET',
      `/orders/${order.id}`,
      { token: customer.token },
    );

    expect(reloaded.body.items[0].unitPrice).toBe('12.50');
    expect(reloaded.body.total).toBe('12.50');
  });

  it("hides one customer's order from another", async () => {
    const { body: order } = await placeOrder(1);
    const { token: otherToken } = await testApp.signUp('other@example.test');

    const response = await testApp.request('GET', `/orders/${order.id}`, {
      token: otherToken,
    });

    expect(response.status).toBe(404);
  });

  it('expires a pending order left behind by a crash and releases its stock', async () => {
    const [stale] = await testApp.query<{ id: number }>(
      `INSERT INTO orders (order_number, user_id, status, subtotal, total, created_at)
       VALUES ('ORD-STALE', $1, 'pending', 37.50, 37.50, now() - interval '20 minutes')
       RETURNING id`,
      [customer.id],
    );
    await testApp.query(
      `INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
       VALUES ($1, $2, 'Mug', 12.50, 3, 37.50)`,
      [stale.id, productId],
    );
    await testApp.query(
      'UPDATE inventories SET quantity = quantity - 3 WHERE product_id = $1',
      [productId],
    );

    const expired = await testApp.app
      .get(OrderExpiryService)
      .expireStaleOrders();

    const [order] = await testApp.query<{ status: string }>(
      'SELECT status FROM orders WHERE id = $1',
      [stale.id],
    );
    expect(expired).toBe(1);
    expect(order.status).toBe('payment_failed');
    expect(await stock()).toBe(5);
  });
});
