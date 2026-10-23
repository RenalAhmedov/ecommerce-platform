import { escapeHtml } from '../../common/utils/escape-html.js';
import type { MailContent } from '../../mail/interfaces/mail-message.interface.js';
import type { Order } from '../entities/order.entity.js';
import type { ShippingAddress } from '../interfaces/shipping-address.interface.js';

export function renderOrderConfirmation(order: Order): MailContent {
  const address = formatAddress(order.shippingAddress);
  const lines = order.items.map(
    (item) =>
      `${item.quantity} x ${item.productName} (${item.unitPrice}) = ${item.lineTotal}`,
  );

  const text = [
    `Hi ${order.user.name},`,
    '',
    `Thank you for your order ${order.orderNumber}. We have received your payment.`,
    '',
    ...lines,
    '',
    `Total: ${order.total}`,
    '',
    'Shipping to:',
    ...address,
  ].join('\n');

  const rows = order.items
    .map(
      (item) =>
        `<tr><td>${escapeHtml(item.productName)}</td><td>${item.quantity}</td><td>${item.unitPrice}</td><td>${item.lineTotal}</td></tr>`,
    )
    .join('');

  const html = [
    `<p>Hi ${escapeHtml(order.user.name)},</p>`,
    `<p>Thank you for your order <strong>${escapeHtml(order.orderNumber)}</strong>. We have received your payment.</p>`,
    `<table cellpadding="6"><thead><tr><th align="left">Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>${rows}</tbody></table>`,
    `<p><strong>Total: ${order.total}</strong></p>`,
    `<p>Shipping to:<br>${address.map(escapeHtml).join('<br>')}</p>`,
  ].join('\n');

  return {
    subject: `Your order ${order.orderNumber} is confirmed`,
    text,
    html,
  };
}

function formatAddress(address: ShippingAddress | null): string[] {
  if (!address) {
    return [];
  }

  return [
    address.fullName,
    address.line1,
    address.line2,
    `${address.postalCode} ${address.city}`,
    address.country,
  ].filter((line): line is string => Boolean(line));
}
