import { Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import type {
  ChargeRequest,
  ChargeResult,
} from '../interfaces/charge.interface.js';
import { PaymentGateway } from '../payment.gateway.js';

const DECLINED_PAYMENT_TOKEN = 'tok_declined';

@Injectable()
export class MockPaymentGateway extends PaymentGateway {
  charge({ token }: ChargeRequest): Promise<ChargeResult> {
    if (token === DECLINED_PAYMENT_TOKEN) {
      return Promise.resolve({ status: 'declined', reason: 'card_declined' });
    }

    return Promise.resolve({
      status: 'succeeded',
      transactionId: `mock_${randomUUID()}`,
    });
  }
}
