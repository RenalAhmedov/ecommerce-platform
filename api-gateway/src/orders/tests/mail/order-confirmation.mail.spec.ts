import type { Order } from '../../entities/order.entity.js';
import { renderOrderConfirmation } from '../../mail/order-confirmation.mail.js';

describe('renderOrderConfirmation', () => {
  const order = {
    orderNumber: 'ORD-20260101-ABCDEF12',
    total: '25.00',
    user: { name: 'Ada <Lovelace>' },
    shippingAddress: {
      fullName: 'Ada Lovelace',
      line1: '1 Main St',
      city: 'Sofia',
      postalCode: '1000',
      country: 'BG',
    },
    items: [
      {
        productName: '<script>alert(1)</script> Mug',
        quantity: 2,
        unitPrice: '12.50',
        lineTotal: '25.00',
      },
    ],
  } as unknown as Order;

  it('puts the order number in the subject', () => {
    expect(renderOrderConfirmation(order).subject).toBe(
      'Your order ORD-20260101-ABCDEF12 is confirmed',
    );
  });

  it('lists items, total and address in the text version', () => {
    const { text } = renderOrderConfirmation(order);

    expect(text).toContain('2 x <script>alert(1)</script> Mug (12.50) = 25.00');
    expect(text).toContain('Total: 25.00');
    expect(text).toContain('1000 Sofia');
  });

  it('escapes customer and product content in the HTML version', () => {
    const { html } = renderOrderConfirmation(order);

    expect(html).not.toContain('<script>');
    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt; Mug');
    expect(html).toContain('Hi Ada &lt;Lovelace&gt;,');
  });

  it('skips the optional second address line when it is missing', () => {
    expect(renderOrderConfirmation(order).text).not.toContain('undefined');
  });
});
