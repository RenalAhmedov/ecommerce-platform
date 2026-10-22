import type {
  ChargeRequest,
  ChargeResult,
} from './interfaces/charge.interface.js';

export abstract class PaymentGateway {
  abstract charge(request: ChargeRequest): Promise<ChargeResult>;
}
