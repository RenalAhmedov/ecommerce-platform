import { MockPaymentGateway } from '../../gateways/mock-payment.gateway.js';

describe('MockPaymentGateway', () => {
  const gateway = new MockPaymentGateway();
  const request = { reference: 'ORD-1', amount: '10.00' };

  it('approves any token with a unique transaction id', async () => {
    const first = await gateway.charge({ ...request, token: 'tok_visa' });
    const second = await gateway.charge({ ...request, token: 'tok_visa' });

    expect(first).toMatchObject({ status: 'succeeded' });
    expect(first).not.toEqual(second);
  });

  it('declines the documented test token', async () => {
    await expect(
      gateway.charge({ ...request, token: 'tok_declined' }),
    ).resolves.toEqual({ status: 'declined', reason: 'card_declined' });
  });
});
