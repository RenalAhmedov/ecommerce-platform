import { Module } from '@nestjs/common';
import { MockPaymentGateway } from './gateways/mock-payment.gateway.js';
import { PaymentGateway } from './payment.gateway.js';

@Module({
  providers: [{ provide: PaymentGateway, useClass: MockPaymentGateway }],
  exports: [PaymentGateway],
})
export class PaymentsModule {}
